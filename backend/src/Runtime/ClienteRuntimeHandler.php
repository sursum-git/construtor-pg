<?php

namespace App\Runtime;

use App\Entity\Cliente;
use App\Repository\ClienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class ClienteRuntimeHandler
{
    private const FIELD_MAP = [
        'id' => 'id',
        'nome' => 'nome',
        'email' => 'email',
        'telefone' => 'telefone',
        'status' => 'status',
        'tipo_pessoa' => 'tipoPessoa',
        'uf' => 'uf',
        'cidade' => 'cidade',
        'razao_social' => 'razaoSocial',
        'cnpj' => 'cnpj',
        'data_cadastro' => 'dataCadastro',
        'valor_total' => 'valorTotal',
        'qtde_pedidos' => 'qtdePedidos',
        'observacao' => 'observacao',
    ];

    public function __construct(
        private readonly ClienteRepository $clientes,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeLockService $locks,
        private readonly RuntimeConcurrencyGuard $concurrency,
        private readonly RuntimeTransactionService $transactions,
    ) {
    }

    public function handle(string $operation, array $payload): array
    {
        $payload = $this->normalizeFormPayload($payload);

        return match ($operation) {
            'read' => $this->read($payload),
            'get' => $this->get($this->payloadId($payload)),
            'create' => $this->create($payload),
            'update' => $this->update($this->payloadId($payload), $payload),
            'delete' => $this->delete($this->payloadId($payload), $payload),
            'bulkActivate' => $this->bulkStatus($payload, 'ATIVO'),
            'bulkInactivate' => $this->bulkStatus($payload, 'INATIVO'),
            'bulkDelete' => $this->bulkDelete($payload),
            'loadCidadesByUf' => $this->loadCidadesByUf((string) ($payload['uf'] ?? '')),
            'validateStatusCliente' => $this->validateStatus((string) ($payload['status'] ?? '')),
            'statusHistory', 'stepHistory' => $this->history($payload),
            'printClienteExcel', 'printClientePdf', 'printClienteCsv' => $this->print($operation, $payload),
            'checkCredit', 'sendWelcome' => $this->action($operation, $payload),
            default => throw new RuntimeHttpException('CLIENTE_OPERATION_NOT_FOUND', 'Operação de clientes não encontrada.', 404, [
                'operation' => $operation,
            ]),
        };
    }

    private function normalizeFormPayload(array $payload): array
    {
        $values = $this->allowedFormValues($payload['values'] ?? []);

        foreach ($values as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            $payload[$field] = $value;
        }

        $payload['values'] = $values;
        if (empty($payload['id'])) {
            $id = $this->payloadId($payload);
            if ($id > 0) {
                $payload['id'] = $id;
            }
        }

        return $payload;
    }

    private function allowedFormValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $allowed = array_fill_keys(array_keys(self::FIELD_MAP), true);
        $result = [];
        foreach ($values as $field => $value) {
            $field = (string) $field;
            if (!isset($allowed[$field])) {
                continue;
            }
            if ($value !== null && !is_scalar($value)) {
                continue;
            }
            $result[$field] = $value;
        }

        return $result;
    }

    private function payloadId(array $payload): int
    {
        foreach (['id', 'clienteId', 'recordId'] as $field) {
            $id = $this->toPositiveInt($payload[$field] ?? null);
            if ($id > 0) {
                return $id;
            }
        }

        foreach (['record', 'values'] as $group) {
            if (!is_array($payload[$group] ?? null)) {
                continue;
            }
            foreach (['id', 'clienteId', 'recordId'] as $field) {
                $id = $this->toPositiveInt($payload[$group][$field] ?? null);
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return 0;
    }

    private function toPositiveInt(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_float($value) && floor($value) === $value) {
            return max(0, (int) $value);
        }
        if (!is_string($value) || !preg_match('/^\d+$/', $value)) {
            return 0;
        }

        return (int) $value;
    }

    private function read(array $payload): array
    {
        $qb = $this->clientes->createQueryBuilder('c');
        $this->applyCustomFilters($qb, $payload['filters'] ?? []);
        $this->applyKendoFilter($qb, $payload['filter'] ?? null);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $this->applySort($qb, $payload['sort'] ?? []);
        $take = max(1, min(500, (int) ($payload['take'] ?? $payload['pageSize'] ?? 20)));
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $rows = $qb->setFirstResult($skip)->setMaxResults($take)->getQuery()->getResult();

        $response = [
            'data' => array_map(fn (Cliente $cliente) => $this->toArray($cliente), $rows),
            'total' => $total,
        ];
        $this->transactions->log('cliente.read', 'Listagem de clientes executada.', metadata: [
            'total' => $total,
            'count' => count($rows),
        ]);

        return $response;
    }

    private function get(int $id): array
    {
        $cliente = $this->findCliente($id);
        $response = $this->toArray($cliente);
        $this->transactions->log('cliente.get', 'Cliente consultado.', after: $response);

        return $response;
    }

    private function create(array $payload): array
    {
        $cliente = new Cliente();
        $this->hydrate($cliente, $payload);
        $this->entityManager->persist($cliente);
        $this->entityManager->flush();

        $after = $this->toArray($cliente);
        $this->transactions->log('cliente.create', 'Cliente incluido.', after: $after);

        return $after;
    }

    private function update(int $id, array $payload): array
    {
        $cliente = $this->findCliente($id);
        $before = $this->toArray($cliente);
        $this->locks->validateWriteLock('cliente', $id, 'update', $payload);
        $this->concurrency->assertExpectedVersion('cliente', 'update', $this->version($cliente), $payload);
        $this->hydrate($cliente, $payload);
        $this->entityManager->flush();

        $after = $this->toArray($cliente);
        $this->transactions->log('cliente.update', 'Cliente alterado.', before: $before, after: $after);
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }

        return $after;
    }

    private function delete(int $id, array $payload): array
    {
        $cliente = $this->findCliente($id);
        $before = $this->toArray($cliente);
        $this->locks->validateWriteLock('cliente', $id, 'delete', $payload);
        $this->concurrency->assertExpectedVersion('cliente', 'delete', $this->version($cliente), $payload);
        $this->entityManager->remove($cliente);
        $this->entityManager->flush();
        $this->transactions->log('cliente.delete', 'Cliente excluido.', before: $before);
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }

        return ['ok' => true];
    }

    private function bulkStatus(array $payload, string $status): array
    {
        $ids = array_map('intval', $payload['ids'] ?? []);
        if (!$ids) {
            throw new RuntimeHttpException('CLIENTE_IDS_REQUIRED', 'Selecione ao menos um cliente.');
        }

        $items = $this->clientes->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        foreach ($items as $cliente) {
            $cliente->setStatus($status);
        }
        $this->entityManager->flush();

        return ['ok' => true, 'count' => count($items)];
    }

    private function bulkDelete(array $payload): array
    {
        $ids = array_map('intval', $payload['ids'] ?? []);
        if (!$ids) {
            throw new RuntimeHttpException('CLIENTE_IDS_REQUIRED', 'Selecione ao menos um cliente.');
        }

        $items = $this->clientes->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        foreach ($items as $cliente) {
            $this->entityManager->remove($cliente);
        }
        $this->entityManager->flush();

        return ['ok' => true, 'count' => count($items)];
    }

    private function loadCidadesByUf(string $uf): array
    {
        $cities = [
            'CE' => [
                ['value' => 'Fortaleza', 'text' => 'Fortaleza'],
                ['value' => 'Sobral', 'text' => 'Sobral'],
                ['value' => 'Juazeiro do Norte', 'text' => 'Juazeiro do Norte'],
            ],
            'SP' => [
                ['value' => 'São Paulo', 'text' => 'São Paulo'],
                ['value' => 'Campinas', 'text' => 'Campinas'],
                ['value' => 'Santos', 'text' => 'Santos'],
            ],
            'RJ' => [
                ['value' => 'Rio de Janeiro', 'text' => 'Rio de Janeiro'],
                ['value' => 'Niterói', 'text' => 'Niterói'],
                ['value' => 'Petrópolis', 'text' => 'Petrópolis'],
            ],
        ];

        return [
            'options' => $cities[strtoupper($uf)] ?? [],
        ];
    }

    private function validateStatus(string $status): array
    {
        if ($status === 'INATIVO') {
            return [
                'effects' => [
                    [
                        'action' => 'required',
                        'target' => 'observacao',
                        'value' => true,
                    ],
                    [
                        'action' => 'showMessage',
                        'message' => 'Informe uma observação ao inativar o cliente.',
                        'type' => 'info',
                    ],
                ],
            ];
        }

        return [
            'effects' => [
                [
                    'action' => 'required',
                    'target' => 'observacao',
                    'value' => false,
                ],
            ],
        ];
    }

    private function history(array $payload): array
    {
        return [
            'items' => [
                [
                    'date' => (new \DateTimeImmutable('-2 days'))->format(DATE_ATOM),
                    'title' => 'Cadastro criado',
                    'description' => 'Registro criado no backend runtime.',
                ],
                [
                    'date' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'title' => 'Consulta executada',
                    'description' => 'Histórico solicitado para o cliente ' . (string) ($payload['id'] ?? ''),
                ],
            ],
        ];
    }

    private function print(string $operation, array $payload): array
    {
        return [
            'ok' => true,
            'format' => str_replace('printCliente', '', $operation),
            'requestedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'recordId' => $this->payloadId($payload) ?: null,
            'receivedValues' => $payload['values'] ?? [],
        ];
    }

    private function action(string $operation, array $payload): array
    {
        return [
            'ok' => true,
            'action' => $operation,
            'requestedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'receivedValues' => $payload['values'] ?? [],
            'recordId' => $this->payloadId($payload) ?: null,
        ];
    }

    private function hydrate(Cliente $cliente, array $payload): void
    {
        $cliente
            ->setNome((string) $this->fieldValue($payload, 'nome', $cliente->getNome()))
            ->setEmail($this->nullableString($this->fieldValue($payload, 'email', $cliente->getEmail())))
            ->setTelefone($this->nullableString($this->fieldValue($payload, 'telefone', $cliente->getTelefone())))
            ->setStatus((string) $this->fieldValue($payload, 'status', $cliente->getStatus()))
            ->setTipoPessoa((string) $this->fieldValue($payload, 'tipo_pessoa', $cliente->getTipoPessoa()))
            ->setUf($this->nullableString($this->fieldValue($payload, 'uf', $cliente->getUf())))
            ->setCidade($this->nullableString($this->fieldValue($payload, 'cidade', $cliente->getCidade())))
            ->setRazaoSocial($this->nullableString($this->fieldValue($payload, 'razao_social', $cliente->getRazaoSocial())))
            ->setCnpj($this->nullableString($this->fieldValue($payload, 'cnpj', $cliente->getCnpj())))
            ->setValorTotal($this->fieldValue($payload, 'valor_total', $cliente->getValorTotal()))
            ->setQtdePedidos($this->nullableInt($this->fieldValue($payload, 'qtde_pedidos', $cliente->getQtdePedidos())))
            ->setObservacao($this->nullableString($this->fieldValue($payload, 'observacao', $cliente->getObservacao())));

        if (array_key_exists('data_cadastro', $payload)) {
            $value = $payload['data_cadastro'];
            $cliente->setDataCadastro($value === null || $value === '' ? null : new \DateTimeImmutable((string) $value));
        }
    }

    private function fieldValue(array $payload, string $field, mixed $fallback): mixed
    {
        return array_key_exists($field, $payload) ? $payload[$field] : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function applySort(QueryBuilder $qb, mixed $sort): void
    {
        $items = is_array($sort) ? $sort : [];
        if (!$items) {
            $qb->addOrderBy('c.id', 'ASC');
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = $this->mapField((string) ($item['field'] ?? ''));
            if (!$field) {
                continue;
            }
            $dir = strtolower((string) ($item['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $qb->addOrderBy('c.' . $field, $dir);
        }
    }

    private function applyCustomFilters(QueryBuilder $qb, mixed $filters): void
    {
        if (!is_array($filters)) {
            return;
        }

        foreach ($filters as $index => $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $value = $filter['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $id = (string) ($filter['id'] ?? '');
            $field = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? 'contains');
            $param = 'customFilter' . $index;

            if ($id === 'busca') {
                $qb->andWhere('LOWER(c.nome) LIKE :' . $param . ' OR LOWER(c.email) LIKE :' . $param)
                    ->setParameter($param, '%' . mb_strtolower((string) $value) . '%');
                continue;
            }

            $property = $this->mapField($field);
            if (!$property) {
                continue;
            }
            $this->applyCondition($qb, 'c.' . $property, $operator, $value, $param);
        }
    }

    private function applyKendoFilter(QueryBuilder $qb, mixed $filter): void
    {
        if (!is_array($filter) || empty($filter['filters'])) {
            return;
        }

        $counter = 0;
        $expression = $this->buildKendoExpression($qb, $filter, $counter);
        if ($expression !== null) {
            $qb->andWhere($expression);
        }
    }

    private function buildKendoExpression(QueryBuilder $qb, array $filter, int &$counter): mixed
    {
        if (isset($filter['filters']) && is_array($filter['filters'])) {
            $parts = [];
            foreach ($filter['filters'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $part = $this->buildKendoExpression($qb, $child, $counter);
                if ($part !== null) {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                return null;
            }
            return strtolower((string) ($filter['logic'] ?? 'and')) === 'or'
                ? $qb->expr()->orX(...$parts)
                : $qb->expr()->andX(...$parts);
        }

        $field = $this->mapField((string) ($filter['field'] ?? ''));
        if (!$field) {
            return null;
        }
        $operator = (string) ($filter['operator'] ?? 'eq');
        $param = 'filter' . (++$counter);
        return $this->buildCondition($qb, 'c.' . $field, $operator, $filter['value'] ?? null, $param);
    }

    private function applyCondition(QueryBuilder $qb, string $field, string $operator, mixed $value, string $param): void
    {
        $condition = $this->buildCondition($qb, $field, $operator, $value, $param);
        if ($condition !== null) {
            $qb->andWhere($condition);
        }
    }

    private function buildCondition(QueryBuilder $qb, string $field, string $operator, mixed $value, string $param): mixed
    {
        $normalized = strtolower($operator);
        if (in_array($normalized, ['isnull', 'isnullorempty'], true)) {
            return $qb->expr()->isNull($field);
        }
        if ($normalized === 'isnotnull') {
            return $qb->expr()->isNotNull($field);
        }
        if ($normalized === 'isempty') {
            $qb->setParameter($param, '');
            return $qb->expr()->eq($field, ':' . $param);
        }
        if ($normalized === 'isnotempty') {
            $qb->setParameter($param, '');
            return $qb->expr()->neq($field, ':' . $param);
        }

        $dbValue = $this->normalizeQueryValue($field, $value);
        if ($normalized === 'contains' || $normalized === 'notcontains') {
            $qb->setParameter($param, '%' . mb_strtolower((string) $dbValue) . '%');
            $expr = 'LOWER(' . $field . ') LIKE :' . $param;
            return $normalized === 'notcontains' ? $qb->expr()->not($expr) : $expr;
        }
        if ($normalized === 'startswith') {
            $qb->setParameter($param, mb_strtolower((string) $dbValue) . '%');
            return 'LOWER(' . $field . ') LIKE :' . $param;
        }
        if ($normalized === 'endswith') {
            $qb->setParameter($param, '%' . mb_strtolower((string) $dbValue));
            return 'LOWER(' . $field . ') LIKE :' . $param;
        }
        if ($normalized === 'neq') {
            $qb->setParameter($param, $dbValue);
            return $qb->expr()->neq($field, ':' . $param);
        }
        if (in_array($normalized, ['gte', 'lte', 'gt', 'lt'], true)) {
            $qb->setParameter($param, $dbValue);
            return $qb->expr()->{$normalized}($field, ':' . $param);
        }
        if ($normalized === 'in' || $normalized === 'notin') {
            $qb->setParameter($param, is_array($value) ? $value : array_map('trim', explode(',', (string) $value)));
            $expr = $qb->expr()->in($field, ':' . $param);
            return $normalized === 'notin' ? $qb->expr()->not($expr) : $expr;
        }

        $qb->setParameter($param, $dbValue);
        return $qb->expr()->eq($field, ':' . $param);
    }

    private function normalizeQueryValue(string $field, mixed $value): mixed
    {
        if (str_ends_with($field, 'dataCadastro') && is_string($value) && $value !== '') {
            return new \DateTimeImmutable($value);
        }
        return $value;
    }

    private function mapField(string $field): ?string
    {
        return self::FIELD_MAP[$field] ?? null;
    }

    private function findCliente(int $id): Cliente
    {
        $cliente = $id > 0 ? $this->clientes->find($id) : null;
        if (!$cliente) {
            throw new RuntimeHttpException('CLIENTE_NOT_FOUND', 'Cliente não encontrado.', 404, [
                'id' => $id,
            ]);
        }
        return $cliente;
    }

    private function toArray(Cliente $cliente): array
    {
        return [
            'id' => $cliente->getId(),
            'nome' => $cliente->getNome(),
            'email' => $cliente->getEmail(),
            'telefone' => $cliente->getTelefone(),
            'status' => $cliente->getStatus(),
            'tipo_pessoa' => $cliente->getTipoPessoa(),
            'uf' => $cliente->getUf(),
            'cidade' => $cliente->getCidade(),
            'razao_social' => $cliente->getRazaoSocial(),
            'cnpj' => $cliente->getCnpj(),
            'data_cadastro' => $cliente->getDataCadastro()?->format('Y-m-d'),
            'valor_total' => $cliente->getValorTotal() === null ? null : (float) $cliente->getValorTotal(),
            'qtde_pedidos' => $cliente->getQtdePedidos(),
            'observacao' => $cliente->getObservacao(),
            '_runtime' => [
                'version' => $this->version($cliente),
                'lastModifiedAt' => $cliente->getUpdatedAt()->format(DATE_ATOM),
            ],
        ];
    }

    private function version(Cliente $cliente): string
    {
        return hash('sha256', 'cliente:' . $cliente->getId() . ':' . $cliente->getUpdatedAt()->format(DATE_ATOM));
    }
}
