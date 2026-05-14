<?php

namespace App\Runtime;

use App\Entity\BuilderEntity;
use App\Odoo\OdooClient;
use App\Repository\BuilderEntityRepository;

class RuntimeOdooEntityActionService
{
    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly RuntimeTransactionService $transactions,
        private readonly OdooClient $odoo,
    ) {
    }

    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $entityCode = trim((string) ($payload['entityCode'] ?? $config['entityCode'] ?? ''));
        $operation = trim((string) ($config['operation'] ?? $payload['operation'] ?? $endpointId));
        $definition = $this->resolveDefinition($entityCode);

        return match ($operation) {
            'read' => $this->read($definition, $payload),
            'get' => $this->get($definition, $payload),
            default => throw new RuntimeHttpException('ENTITY_API_ODOO_OPERATION_NOT_SUPPORTED', 'Entidade Odoo suporta apenas read e get nesta etapa.', 422, [
                'entityCode' => $entityCode,
                'operation' => $operation,
                'screenId' => $screenId,
                'endpointId' => $endpointId,
            ]),
        };
    }

    private function read(array $definition, array $payload): array
    {
        $startedAt = microtime(true);
        $session = $this->odoo->openSession($definition['odoo']);
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $take = max(1, min(500, (int) ($payload['take'] ?? $payload['pageSize'] ?? ($definition['odoo']['defaultLimit'] ?? 80))));
        $domain = $this->mergeDomains(
            $definition['odoo']['defaultDomain'] ?? [],
            $this->translateFilterToDomain($payload['filter'] ?? null, $definition['fieldsByCode'])
        );
        $order = $this->translateSortToOrder($payload['sort'] ?? [], $definition['fieldsByCode'], (string) ($definition['odoo']['defaultOrder'] ?? ''));
        $fields = array_values(array_unique(array_map(static fn (array $field): string => (string) $field['jsonPath'], $definition['fields'])));
        $records = $this->odoo->searchReadWithSession($session, [
            'domain' => $domain,
            'fields' => $fields,
            'offset' => $skip,
            'limit' => $take,
            'order' => $order,
            'context' => $definition['odoo']['defaultContext'] ?? [],
        ]);
        $total = $this->odoo->searchCountWithSession($session, $domain, $definition['odoo']['defaultContext'] ?? []);
        $rows = array_map(fn (mixed $item): array => $this->mapItem($definition, is_array($item) ? $item : []), is_array($records) ? $records : []);

        $this->transactions->log($definition['entityCode'] . '.odoo.read', 'Consulta Odoo executada.', metadata: [
            'entityCode' => $definition['entityCode'],
            'transport' => $definition['odoo']['transport'],
            'model' => $definition['odoo']['model'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'itemsCount' => count($rows),
            'total' => $total,
        ]);

        return [
            'data' => array_values($rows),
            'total' => $total,
        ];
    }

    private function get(array $definition, array $payload): array
    {
        $startedAt = microtime(true);
        $session = $this->odoo->openSession($definition['odoo']);
        $id = $this->extractRecordId($definition, $payload);
        $fields = array_values(array_unique(array_map(static fn (array $field): string => (string) $field['jsonPath'], $definition['fields'])));
        $records = $this->odoo->readWithSession($session, [(int) $id], $fields, $definition['odoo']['defaultContext'] ?? []);
        $record = is_array($records) && isset($records[0]) && is_array($records[0]) ? $records[0] : null;
        if (!$record) {
            throw new RuntimeHttpException('ODOO_RECORD_NOT_FOUND', 'Registro Odoo nao encontrado.', 404, [
                'entityCode' => $definition['entityCode'],
                'id' => $id,
            ]);
        }
        $mapped = $this->mapItem($definition, $record);

        $this->transactions->log($definition['entityCode'] . '.odoo.get', 'Detalhe Odoo consultado.', after: $mapped, metadata: [
            'entityCode' => $definition['entityCode'],
            'transport' => $definition['odoo']['transport'],
            'model' => $definition['odoo']['model'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $mapped;
    }

    private function resolveDefinition(string $entityCode): array
    {
        if ($entityCode === '') {
            throw new RuntimeHttpException('ENTITY_CODE_REQUIRED', 'Informe a entidade da operacao.', 422);
        }

        $entity = $this->entities->findOneBy(['code' => $entityCode]);
        if (!$entity instanceof BuilderEntity) {
            throw new RuntimeHttpException('ENTITY_METADATA_NOT_CONFIGURED', 'Entidade nao configurada no construtor.', 422, [
                'entityCode' => $entityCode,
            ]);
        }
        if ($entity->getEntityType() !== 'api') {
            throw new RuntimeHttpException('ENTITY_NOT_API', 'A entidade informada nao e do tipo API.', 422, [
                'entityCode' => $entityCode,
                'entityType' => $entity->getEntityType(),
            ]);
        }

        $apiSource = is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : [];
        if ((string) ($apiSource['providerType'] ?? '') !== 'odoo') {
            throw new RuntimeHttpException('ENTITY_NOT_ODOO_PROVIDER', 'A entidade API informada nao usa o provedor Odoo.', 422, [
                'entityCode' => $entityCode,
            ]);
        }
        $odoo = is_array($apiSource['odoo'] ?? null) ? $apiSource['odoo'] : [];
        if (!$odoo) {
            throw new RuntimeHttpException('ENTITY_ODOO_NOT_CONFIGURED', 'Entidade Odoo sem configuracao valida.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        $fields = [];
        $primaryKey = '';
        foreach ($entity->getFields() as $field) {
            $options = $field->getOptions();
            $apiField = is_array($options['api'] ?? null) ? $options['api'] : [];
            $jsonPath = trim((string) ($apiField['jsonPath'] ?? $field->getCode()));
            $odooField = is_array($options['odoo'] ?? null) ? $options['odoo'] : [];
            $fields[] = [
                'code' => $field->getCode(),
                'label' => $field->getLabel(),
                'dataType' => $field->getDataType(),
                'jsonPath' => $jsonPath,
                'primaryKey' => $field->isPrimaryKey(),
                'odooFieldType' => (string) ($odooField['fieldType'] ?? ''),
            ];
            if ($field->isPrimaryKey()) {
                $primaryKey = $field->getCode();
            }
        }

        if ($primaryKey === '') {
            throw new RuntimeHttpException('ENTITY_API_PRIMARY_KEY_REQUIRED', 'Entidade Odoo precisa definir um campo chave primaria.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        return [
            'entityCode' => $entityCode,
            'primaryKey' => $primaryKey,
            'odoo' => $odoo,
            'fields' => $fields,
            'fieldsByCode' => array_column($fields, null, 'code'),
        ];
    }

    private function mapItem(array $definition, array $item): array
    {
        $mapped = [];
        foreach ($definition['fields'] as $field) {
            $value = $item[$field['jsonPath']] ?? null;
            if (is_array($value) && (string) $field['odooFieldType'] === 'many2one') {
                $mapped[$field['code']] = isset($value[1]) ? (string) $value[1] : (isset($value[0]) ? (string) $value[0] : '');
                continue;
            }
            $mapped[$field['code']] = $value;
        }

        return $mapped;
    }

    private function extractRecordId(array $definition, array $payload): int
    {
        foreach (['id', $definition['primaryKey']] as $field) {
            $value = $payload[$field] ?? (is_array($payload['record'] ?? null) ? ($payload['record'][$field] ?? null) : null);
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        throw new RuntimeHttpException('ENTITY_API_ID_REQUIRED', 'Informe o identificador do registro para consultar o detalhe.', 422, [
            'entityCode' => $definition['entityCode'],
            'primaryKey' => $definition['primaryKey'],
        ]);
    }

    private function translateSortToOrder(mixed $sortConfig, array $fieldsByCode, string $fallback): string
    {
        $sorts = is_array($sortConfig) ? $sortConfig : [];
        $parts = [];
        foreach ($sorts as $sort) {
            if (!is_array($sort)) {
                continue;
            }
            $fieldCode = (string) ($sort['field'] ?? '');
            $field = $fieldsByCode[$fieldCode] ?? null;
            if (!is_array($field)) {
                continue;
            }
            $jsonPath = (string) ($field['jsonPath'] ?? '');
            if ($jsonPath === '' || str_contains($jsonPath, '.')) {
                continue;
            }
            $dir = strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $parts[] = $jsonPath . ' ' . $dir;
        }

        return $parts ? implode(', ', $parts) : $fallback;
    }

    private function translateFilterToDomain(mixed $filter, array $fieldsByCode): array
    {
        if (!is_array($filter)) {
            return [];
        }
        if (is_array($filter['filters'] ?? null)) {
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? '|' : '&';
            $parts = [];
            foreach ($filter['filters'] as $entry) {
                $domain = $this->translateFilterToDomain($entry, $fieldsByCode);
                if ($domain) {
                    $parts[] = $domain;
                }
            }
            if (!$parts) {
                return [];
            }
            if (count($parts) === 1) {
                return $parts[0];
            }

            $flat = [];
            for ($index = 0; $index < count($parts) - 1; ++$index) {
                $flat[] = $logic;
            }
            foreach ($parts as $part) {
                foreach ($part as $token) {
                    $flat[] = $token;
                }
            }

            return $flat;
        }

        $fieldCode = (string) ($filter['field'] ?? '');
        $field = $fieldsByCode[$fieldCode] ?? null;
        if (!is_array($field)) {
            return [];
        }
        $jsonPath = (string) ($field['jsonPath'] ?? '');
        if ($jsonPath === '' || str_contains($jsonPath, '.')) {
            return [];
        }
        $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
        $value = $filter['value'] ?? null;
        $odooOperator = match ($operator) {
            'contains' => 'ilike',
            'startswith' => 'ilike',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            default => '=',
        };
        if ($operator === 'startswith') {
            $value = (string) $value . '%';
        }

        return [[$jsonPath, $odooOperator, $value]];
    }

    private function mergeDomains(array $base, array $extra): array
    {
        if (!$base) {
            return $extra;
        }
        if (!$extra) {
            return $base;
        }

        return array_merge($base, $extra);
    }
}
