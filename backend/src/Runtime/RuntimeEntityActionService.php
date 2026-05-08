<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class RuntimeEntityActionService
{
    public function __construct(
        private readonly RuntimeEntityDefinitionResolver $definitions,
        private readonly Connection $connection,
        private readonly RuntimeLockService $locks,
        private readonly RuntimeConcurrencyGuard $concurrency,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeBusinessRuleRegistry $rules,
        private readonly RuntimeSituationService $situations,
    ) {
    }

    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $entityCode = (string) ($payload['entityCode'] ?? $config['entityCode'] ?? '');
        $operation = (string) ($config['operation'] ?? $payload['operation'] ?? $endpointId);
        $actionId = (string) ($payload['actionId'] ?? $config['actionId'] ?? $operation);
        $definition = $this->definitions->resolve($entityCode);
        $payload = $this->withResolvedRuntimePayload($definition, $payload, $actionId, $operation);

        return match ($operation) {
            'read' => $this->read($definition, $payload),
            'get' => $this->get($definition, $this->payloadId($definition, $payload)),
            'create' => $this->create($definition, $payload, $actionId),
            'update', 'edit' => $this->update($definition, $this->payloadId($definition, $payload), $payload, $actionId),
            'delete' => $this->delete($definition, $this->payloadId($definition, $payload), $payload, $actionId),
            default => throw new RuntimeHttpException('ENTITY_OPERATION_NOT_FOUND', 'Operacao generica da entidade nao encontrada.', 404, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
                'operation' => $operation,
                'entityCode' => $entityCode,
            ]),
        };
    }

    private function read(array $definition, array $payload): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...$this->selectColumns($definition, 't'))
            ->from($definition['quotedTableName'], 't');
        $this->applyCustomFilters($qb, $definition, $payload['filters'] ?? []);
        $this->applyKendoFilter($qb, $definition, $payload['filter'] ?? null);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(*)')->executeQuery()->fetchOne();

        $this->applySort($qb, $definition, $payload['sort'] ?? []);
        $take = max(1, min(500, (int) ($payload['take'] ?? $payload['pageSize'] ?? 20)));
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $rows = $qb->setFirstResult($skip)->setMaxResults($take)->executeQuery()->fetchAllAssociative();
        $data = array_map(fn (array $row) => $this->formatRow($definition, $row), $rows);

        $this->transactions->log($definition['entityCode'] . '.read', 'Listagem executada pelo runtime generico.', metadata: [
            'entityCode' => $definition['entityCode'],
            'total' => $total,
            'count' => count($data),
        ]);

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    private function get(array $definition, string|int $id): array
    {
        $row = $this->findRow($definition, $id);
        $response = $this->formatRow($definition, $row);
        $this->transactions->log($definition['entityCode'] . '.get', 'Registro consultado pelo runtime generico.', after: $response);

        return $response;
    }

    private function create(array $definition, array $payload, string $actionId): array
    {
        $values = $this->extractValues($definition, $payload, true);
        $values = $this->situations->applyCreateDefaults($definition, $values);
        $context = new RuntimeBusinessRuleContext($definition, 'create', $actionId, $payload, $values);
        $this->rules->beforeValidate($context);
        $values = $context->getValues();
        $this->validateRequiredValues($definition, $values, true);
        $this->validateDeclarativeRules($definition, $values);
        $context->setValues($values);
        $this->rules->beforePersist($context);
        $values = $context->getValues();
        $situationTransition = $this->situations->validateCreate($definition, $values, $actionId);
        $dbValues = $this->toDatabaseValues($definition, $values);
        $this->applyTimestampColumns($definition, $dbValues, true);

        $columns = array_keys($dbValues);
        if (!$columns) {
            throw new RuntimeValidationException('ENTITY_VALUES_REQUIRED', 'Informe os dados para gravar.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => [['type' => 'error', 'message' => 'Nenhum campo permitido foi informado.']],
            ]);
        }

        $params = [];
        $placeholders = [];
        foreach ($columns as $index => $column) {
            $param = 'v' . $index;
            $params[$param] = $dbValues[$column];
            $placeholders[] = $this->sqlValuePlaceholder($definition, $column, ':' . $param);
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
            $definition['quotedTableName'],
            implode(', ', array_map(fn (string $column) => $this->quote($column), $columns)),
            implode(', ', $placeholders),
            $this->quote($definition['primaryColumn']),
        );
        $id = $this->connection->fetchOne($sql, $params);
        $after = $this->formatRow($definition, $this->findRow($definition, $id));
        $context->setAfter($after);
        $this->situations->logTransition($definition, $situationTransition, [], $after);
        $this->transactions->log($definition['entityCode'] . '.create', 'Registro incluido pelo runtime generico.', after: $after, metadata: [
            'entityCode' => $definition['entityCode'],
            'fields' => array_keys($values),
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
        ]);
        $this->rules->afterPersist($context);
        $this->rules->afterCommit($context);

        return $this->situations->applyTransitionEffects($after, $situationTransition);
    }

    private function update(array $definition, string|int $id, array $payload, string $actionId): array
    {
        $beforeRow = $this->findRow($definition, $id);
        $before = $this->formatRow($definition, $beforeRow);
        $this->locks->validateWriteLock($definition['entityCode'], $id, 'update', $payload);
        $this->concurrency->assertExpectedVersion($definition['entityCode'], 'update', $before['_runtime']['version'] ?? null, $payload);
        $values = $this->extractValues($definition, $payload, false);
        $context = new RuntimeBusinessRuleContext($definition, 'update', $actionId, $payload, $values, $before);
        $this->rules->beforeValidate($context);
        $values = $context->getValues();
        $this->validateRequiredValues($definition, $values, false);
        $this->validateDeclarativeRules($definition, array_merge($before, $values));
        $context->setValues($values);
        $this->rules->beforePersist($context);
        $values = $context->getValues();
        $situationTransition = $this->situations->validateUpdate($definition, $before, $values, $actionId);
        $dbValues = $this->toDatabaseValues($definition, $values);
        $this->applyTimestampColumns($definition, $dbValues, false);

        if (!$dbValues) {
            throw new RuntimeValidationException('ENTITY_VALUES_REQUIRED', 'Informe os dados para gravar.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => [['type' => 'error', 'message' => 'Nenhum campo permitido foi informado.']],
            ]);
        }

        $sets = [];
        $params = ['id' => $id];
        foreach ($dbValues as $column => $value) {
            $param = 'v_' . $column;
            $sets[] = $this->quote($column) . ' = ' . $this->sqlValuePlaceholder($definition, $column, ':' . $param);
            $params[$param] = $value;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $definition['quotedTableName'],
            implode(', ', $sets),
            $this->quote($definition['primaryColumn']),
        );
        $this->connection->executeStatement($sql, $params);
        $after = $this->formatRow($definition, $this->findRow($definition, $id));
        $context->setAfter($after);
        $this->situations->logTransition($definition, $situationTransition, $before, $after);
        $this->transactions->log($definition['entityCode'] . '.update', 'Registro alterado pelo runtime generico.', before: $before, after: $after, metadata: [
            'entityCode' => $definition['entityCode'],
            'fields' => array_keys($values),
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
        ]);
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }
        $this->rules->afterPersist($context);
        $this->rules->afterCommit($context);

        return $this->situations->applyTransitionEffects($after, $situationTransition);
    }

    private function delete(array $definition, string|int $id, array $payload, string $actionId): array
    {
        $before = $this->formatRow($definition, $this->findRow($definition, $id));
        $this->locks->validateWriteLock($definition['entityCode'], $id, 'delete', $payload);
        $this->concurrency->assertExpectedVersion($definition['entityCode'], 'delete', $before['_runtime']['version'] ?? null, $payload);
        $context = new RuntimeBusinessRuleContext($definition, 'delete', $actionId, $payload, [], $before);
        $this->rules->beforeValidate($context);
        $this->rules->beforePersist($context);
        $this->connection->delete($definition['tableName'], [
            $definition['primaryColumn'] => $id,
        ]);
        $this->transactions->log($definition['entityCode'] . '.delete', 'Registro excluido pelo runtime generico.', before: $before, metadata: [
            'entityCode' => $definition['entityCode'],
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
        ]);
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }
        $this->rules->afterPersist($context);
        $this->rules->afterCommit($context);

        return ['ok' => true];
    }

    private function withResolvedRuntimePayload(array $definition, array $payload, string $actionId, string $operation): array
    {
        $payload['entityCode'] = $definition['entityCode'];
        $payload['actionId'] = $payload['actionId'] ?? $actionId;
        $payload['operation'] = $payload['operation'] ?? $operation;
        if (empty($payload['id'])) {
            $id = $this->payloadId($definition, $payload);
            if ($id !== '') {
                $payload['id'] = $id;
            }
        }

        return $payload;
    }

    private function extractValues(array $definition, array $payload, bool $create): array
    {
        $source = is_array($payload['values'] ?? null) ? $payload['values'] : $payload;
        $values = [];
        foreach ($source as $field => $value) {
            $field = (string) $field;
            if (str_starts_with($field, '_') || !isset($definition['fields'][$field])) {
                continue;
            }
            $config = $definition['fields'][$field];
            if (!$config['writable']) {
                continue;
            }
            if ($value !== null && !is_scalar($value) && $config['dataType'] !== 'json') {
                continue;
            }
            if (!$create && !array_key_exists($field, $source)) {
                continue;
            }
            $values[$field] = $this->normalizeValue($config, $value);
        }

        return $values;
    }

    private function validateRequiredValues(array $definition, array $values, bool $create): void
    {
        $messages = [];
        foreach ($definition['fields'] as $field => $config) {
            if (!$config['writable'] || !$config['required']) {
                continue;
            }
            if (!$create && !array_key_exists($field, $values)) {
                continue;
            }
            if (!array_key_exists($field, $values) || $values[$field] === null || $values[$field] === '') {
                $messages[] = [
                    'field' => $field,
                    'type' => 'error',
                    'message' => ($config['label'] ?: $field) . ' e obrigatorio.',
                ];
            }
        }

        if ($messages) {
            throw new RuntimeValidationException('BUSINESS_VALIDATION_FAILED', 'Existem inconsistencias no formulario.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => $messages,
            ]);
        }
    }

    private function validateDeclarativeRules(array $definition, array $values): void
    {
        $rules = $definition['metadata']['rules'] ?? [];
        $messages = [];
        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (!is_array($rule) || ($rule['type'] ?? '') !== 'requiredWhen') {
                continue;
            }
            $when = is_array($rule['when'] ?? null) ? $rule['when'] : [];
            $field = (string) ($rule['field'] ?? '');
            $whenField = (string) ($when['field'] ?? '');
            if ($field === '' || $whenField === '' || !isset($definition['fields'][$field])) {
                continue;
            }
            $expected = $when['equals'] ?? null;
            if (($values[$whenField] ?? null) === $expected && trim((string) ($values[$field] ?? '')) === '') {
                $messages[] = [
                    'field' => $field,
                    'type' => 'error',
                    'message' => (string) ($rule['message'] ?? (($definition['fields'][$field]['label'] ?: $field) . ' e obrigatorio.')),
                ];
            }
        }

        if ($messages) {
            throw new RuntimeValidationException('BUSINESS_VALIDATION_FAILED', 'Existem inconsistencias no formulario.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => $messages,
            ]);
        }
    }

    private function toDatabaseValues(array $definition, array $values): array
    {
        $dbValues = [];
        foreach ($values as $field => $value) {
            $dbValues[$definition['fields'][$field]['column']] = $value;
        }

        return $dbValues;
    }

    private function applyTimestampColumns(array $definition, array &$dbValues, bool $create): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($create && isset($definition['dbColumns']['created_at']) && !array_key_exists('created_at', $dbValues)) {
            $dbValues['created_at'] = $now;
        }
        if (isset($definition['dbColumns']['updated_at']) && !array_key_exists('updated_at', $dbValues)) {
            $dbValues['updated_at'] = $now;
        }
    }

    private function normalizeValue(array $field, mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }
        if ($value === null) {
            return null;
        }

        return match ($field['dataType']) {
            'integer' => (int) $value,
            'decimal', 'number', 'currency' => (float) $value,
            'boolean' => (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false) ? 'true' : 'false',
            'date' => (new \DateTimeImmutable((string) $value))->format('Y-m-d'),
            'datetime' => (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'),
            'json' => $this->normalizeJsonValue($field, $value),
            default => (string) $value,
        };
    }

    private function normalizeJsonValue(array $field, mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 'null';
        }

        try {
            json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeValidationException('INVALID_JSON_VALUE', 'JSON invalido.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => [[
                    'field' => $field['code'] ?? null,
                    'type' => 'error',
                    'message' => ($field['label'] ?: 'Campo JSON') . ' deve conter um JSON valido.',
                ]],
            ]);
        }

        return $text;
    }

    private function findRow(array $definition, string|int $id): array
    {
        if ($id === '' || $id === 0 || $id === '0') {
            throw new RuntimeHttpException('RECORD_ID_REQUIRED', 'Informe o registro.', 422);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...$this->selectColumns($definition, 't'))
            ->from($definition['quotedTableName'], 't')
            ->where('t.' . $this->quote($definition['primaryColumn']) . ' = :id')
            ->setParameter('id', $id);
        $row = $qb->executeQuery()->fetchAssociative();
        if (!$row) {
            throw new RuntimeHttpException('RECORD_NOT_FOUND', 'Registro nao encontrado.', 404, [
                'entityCode' => $definition['entityCode'],
                'id' => $id,
            ]);
        }

        return $row;
    }

    private function selectColumns(array $definition, string $alias): array
    {
        $columns = [];
        foreach ($definition['fields'] as $field => $config) {
            if ($config['readable']) {
                $columns[] = $alias . '.' . $this->quote($config['column']) . ' AS ' . $this->quote($field);
            }
        }
        if (isset($definition['dbColumns']['updated_at']) && !isset($definition['fields']['updated_at'])) {
            $columns[] = $alias . '.' . $this->quote('updated_at') . ' AS _runtime_updated_at';
        }

        return $columns ?: [$alias . '.' . $this->quote($definition['primaryColumn']) . ' AS ' . $this->quote($definition['primaryKey'])];
    }

    private function formatRow(array $definition, array $row): array
    {
        $result = [];
        foreach ($definition['fields'] as $field => $config) {
            if (!$config['readable']) {
                continue;
            }
            $result[$field] = $this->formatValue($config, $row[$field] ?? null);
        }

        $versionSource = (string) ($row['_runtime_updated_at'] ?? json_encode($result, JSON_UNESCAPED_UNICODE));
        $result['_runtime'] = [
            'version' => hash('sha256', $definition['entityCode'] . ':' . ($result[$definition['primaryKey']] ?? '') . ':' . $versionSource),
            'lastModifiedAt' => $this->formatDateTime($row['_runtime_updated_at'] ?? null),
        ];

        return $this->situations->decorateRow($definition, $result);
    }

    private function formatValue(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field['dataType']) {
            'integer' => (int) $value,
            'decimal', 'number', 'currency' => (float) $value,
            'boolean' => (bool) $value,
            'date' => substr((string) $value, 0, 10),
            'json' => $this->formatJsonValue($value),
            default => $value,
        };
    }

    private function formatJsonValue(mixed $value): string
    {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } catch (\Throwable) {
                return $value;
            }
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
    }

    private function payloadId(array $definition, array $payload): string|int
    {
        $primaryKey = $definition['primaryKey'];
        foreach ([$primaryKey, 'id', 'recordId'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_scalar($value) && $value !== '') {
                return is_int($value) ? $value : (string) $value;
            }
        }
        foreach (['record', 'values'] as $group) {
            if (!is_array($payload[$group] ?? null)) {
                continue;
            }
            foreach ([$primaryKey, 'id', 'recordId'] as $field) {
                $value = $payload[$group][$field] ?? null;
                if (is_scalar($value) && $value !== '') {
                    return is_int($value) ? $value : (string) $value;
                }
            }
        }

        return '';
    }

    private function applySort(QueryBuilder $qb, array $definition, mixed $sort): void
    {
        $items = is_array($sort) ? $sort : [];
        if (!$items) {
            $qb->addOrderBy('t.' . $this->quote($definition['primaryColumn']), 'ASC');
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = (string) ($item['field'] ?? '');
            if (!isset($definition['fields'][$field])) {
                continue;
            }
            $dir = strtolower((string) ($item['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $qb->addOrderBy('t.' . $this->quote($definition['fields'][$field]['column']), $dir);
        }
    }

    private function applyCustomFilters(QueryBuilder $qb, array $definition, mixed $filters): void
    {
        if (!is_array($filters)) {
            return;
        }
        $counter = 0;
        foreach ($filters as $filter) {
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
            if ($id === 'busca') {
                $parts = [];
                foreach (['nome', 'email'] as $searchField) {
                    if (isset($definition['fields'][$searchField])) {
                        $param = 'customSearch' . (++$counter);
                        $parts[] = 'LOWER(t.' . $this->quote($definition['fields'][$searchField]['column']) . ') LIKE :' . $param;
                        $qb->setParameter($param, '%' . mb_strtolower((string) $value) . '%');
                    }
                }
                if ($parts) {
                    $qb->andWhere('(' . implode(' OR ', $parts) . ')');
                }
                continue;
            }
            if (!isset($definition['fields'][$field])) {
                continue;
            }
            $param = 'customFilter' . (++$counter);
            $condition = $this->buildCondition($qb, $definition, $field, $operator, $value, $param);
            if ($condition !== null) {
                $qb->andWhere($condition);
            }
        }
    }

    private function applyKendoFilter(QueryBuilder $qb, array $definition, mixed $filter): void
    {
        if (!is_array($filter) || empty($filter['filters'])) {
            return;
        }
        $counter = 0;
        $expression = $this->buildKendoExpression($qb, $definition, $filter, $counter);
        if ($expression !== null) {
            $qb->andWhere($expression);
        }
    }

    private function buildKendoExpression(QueryBuilder $qb, array $definition, array $filter, int &$counter): ?string
    {
        if (isset($filter['filters']) && is_array($filter['filters'])) {
            $parts = [];
            foreach ($filter['filters'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $part = $this->buildKendoExpression($qb, $definition, $child, $counter);
                if ($part !== null) {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                return null;
            }
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? ' OR ' : ' AND ';
            return '(' . implode($logic, $parts) . ')';
        }

        $field = (string) ($filter['field'] ?? '');
        if (!isset($definition['fields'][$field])) {
            return null;
        }
        $param = 'filter' . (++$counter);
        return $this->buildCondition($qb, $definition, $field, (string) ($filter['operator'] ?? 'eq'), $filter['value'] ?? null, $param);
    }

    private function buildCondition(QueryBuilder $qb, array $definition, string $field, string $operator, mixed $value, string $param): ?string
    {
        $column = 't.' . $this->quote($definition['fields'][$field]['column']);
        $normalized = strtolower($operator);
        if (in_array($normalized, ['isnull', 'isnullorempty'], true)) {
            return $column . ' IS NULL';
        }
        if ($normalized === 'isnotnull') {
            return $column . ' IS NOT NULL';
        }
        if ($normalized === 'isempty') {
            $qb->setParameter($param, '');
            return $column . ' = :' . $param;
        }
        if ($normalized === 'isnotempty') {
            $qb->setParameter($param, '');
            return $column . ' <> :' . $param;
        }
        if (in_array($normalized, ['contains', 'notcontains', 'startswith', 'endswith'], true)) {
            $needle = mb_strtolower((string) $value);
            $pattern = match ($normalized) {
                'startswith' => $needle . '%',
                'endswith' => '%' . $needle,
                default => '%' . $needle . '%',
            };
            $qb->setParameter($param, $pattern);
            $condition = 'LOWER(' . $column . ') LIKE :' . $param;
            return $normalized === 'notcontains' ? 'NOT (' . $condition . ')' : $condition;
        }
        if (in_array($normalized, ['neq', 'gte', 'lte', 'gt', 'lt'], true)) {
            $qb->setParameter($param, $this->normalizeValue($definition['fields'][$field], $value));
            $operatorSql = ['neq' => '<>', 'gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<'][$normalized];
            return $column . ' ' . $operatorSql . ' :' . $param;
        }

        $qb->setParameter($param, $this->normalizeValue($definition['fields'][$field], $value));
        return $column . ' = :' . $param;
    }

    private function quote(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    private function sqlValuePlaceholder(array $definition, string $column, string $placeholder): string
    {
        foreach ($definition['fields'] as $field) {
            if (($field['column'] ?? null) === $column && ($field['dataType'] ?? null) === 'json') {
                return 'CAST(' . $placeholder . ' AS JSON)';
            }
        }

        return $placeholder;
    }
}
