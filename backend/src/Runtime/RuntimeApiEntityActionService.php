<?php

namespace App\Runtime;

use App\Entity\BuilderEntity;
use App\Repository\BuilderEntityRepository;

class RuntimeApiEntityActionService
{
    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeExternalJsonClient $externalJsonClient,
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
            'create' => $this->create($definition, $payload),
            'update' => $this->update($definition, $payload),
            'delete' => $this->delete($definition, $payload),
            default => throw new RuntimeHttpException('ENTITY_API_OPERATION_NOT_SUPPORTED', 'Entidade API suporta apenas read, get, create, update e delete.', 422, [
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
        $response = $this->requestEndpoint($definition, $definition['apiSource']['listEndpoint'], $definition['apiSource']['authHeaders']);
        $items = $this->extractItems($response['body'], (string) $definition['apiSource']['listResponse']['itemsPath']);
        $data = array_map(fn (mixed $item): array => $this->mapItem($definition, is_array($item) ? $item : []), $items);
        $data = $this->enrichRows($definition, $data);
        $data = $this->applyFilter($data, $payload['filter'] ?? null);
        $data = $this->applySort($data, $payload['sort'] ?? []);
        $totalPath = (string) ($definition['apiSource']['listResponse']['totalPath'] ?? '');
        $total = $totalPath !== '' && $totalPath !== '$'
            ? (int) ($this->extractByPath($response['body'], $totalPath) ?? count($data))
            : count($data);
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $take = max(1, min(500, (int) ($payload['take'] ?? $payload['pageSize'] ?? 20)));
        $paged = array_slice($data, $skip, $take);

        $this->transactions->log($definition['entityCode'] . '.api.read', 'Consulta de entidade API executada.', metadata: [
            'entityCode' => $definition['entityCode'],
            'endpoint' => $definition['apiSource']['listEndpoint']['url'],
            'httpStatus' => $response['status'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'itemsCount' => count($paged),
            'total' => $total,
        ]);

        return [
            'data' => array_values($paged),
            'total' => $total,
        ];
    }

    private function get(array $definition, array $payload): array
    {
        $id = $this->extractRecordId($definition, $payload);
        $detailEndpoint = is_array($definition['apiSource']['detailEndpoint'] ?? null) ? $definition['apiSource']['detailEndpoint'] : null;
        if ($detailEndpoint) {
            $startedAt = microtime(true);
            $response = $this->requestEndpoint($definition, $detailEndpoint, $definition['apiSource']['authHeaders'], [
                'id' => $id,
                $definition['primaryKey'] => $id,
            ]);
            $path = (string) ($definition['apiSource']['detailResponse']['itemPath'] ?? '$');
            $item = $this->extractByPath($response['body'], $path);
            if (!is_array($item)) {
                throw new RuntimeHttpException('ENTITY_API_DETAIL_INVALID', 'Resposta da API externa nao retornou um objeto valido para detalhe.', 422, [
                    'entityCode' => $definition['entityCode'],
                    'itemPath' => $path,
                ]);
            }
            $mapped = $this->enrichRows($definition, [$this->mapItem($definition, $item)])[0] ?? [];
            $this->transactions->log($definition['entityCode'] . '.api.get', 'Detalhe de entidade API consultado.', after: $mapped, metadata: [
                'entityCode' => $definition['entityCode'],
                'endpoint' => $detailEndpoint['url'],
                'httpStatus' => $response['status'],
                'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $mapped;
        }

        if (is_array($payload['record'] ?? null)) {
            $record = $payload['record'];
            $record = $this->enrichRows($definition, [$record])[0] ?? $record;
            $this->transactions->log($definition['entityCode'] . '.api.get', 'Detalhe de entidade API reaproveitado da listagem.', after: $record, metadata: [
                'entityCode' => $definition['entityCode'],
                'source' => 'grid-record',
            ]);

            return $record;
        }

        throw new RuntimeHttpException('ENTITY_API_DETAIL_UNAVAILABLE', 'A entidade API nao possui endpoint de detalhe configurado.', 422, [
            'entityCode' => $definition['entityCode'],
        ]);
    }

    private function create(array $definition, array $payload): array
    {
        $endpoint = is_array($definition['apiSource']['createEndpoint'] ?? null) ? $definition['apiSource']['createEndpoint'] : null;
        if (!$endpoint) {
            throw new RuntimeHttpException('ENTITY_API_CREATE_UNAVAILABLE', 'A entidade API nao possui operacao de inclusao configurada.', 422, [
                'entityCode' => $definition['entityCode'],
            ]);
        }

        $record = $this->extractSubmittedRecord($payload);
        $requestPayload = $this->buildWritePayload($definition, $record, false);
        $startedAt = microtime(true);
        $response = $this->requestEndpoint($definition, $endpoint, $definition['apiSource']['authHeaders'], [], $requestPayload);
        $saved = $this->extractWriteResponseRecord($definition, $response['body'], 'createResponse', $record);

        $this->transactions->log($definition['entityCode'] . '.api.create', 'Registro incluido em API externa.', after: $saved, metadata: [
            'entityCode' => $definition['entityCode'],
            'endpoint' => $endpoint['url'],
            'httpStatus' => $response['status'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $saved;
    }

    private function update(array $definition, array $payload): array
    {
        $endpoint = is_array($definition['apiSource']['updateEndpoint'] ?? null) ? $definition['apiSource']['updateEndpoint'] : null;
        if (!$endpoint) {
            throw new RuntimeHttpException('ENTITY_API_UPDATE_UNAVAILABLE', 'A entidade API nao possui operacao de alteracao configurada.', 422, [
                'entityCode' => $definition['entityCode'],
            ]);
        }

        $record = $this->extractSubmittedRecord($payload);
        $id = $this->extractRecordId($definition, ['record' => $record] + $payload);
        $requestPayload = $this->buildWritePayload($definition, $record, true);
        $startedAt = microtime(true);
        $response = $this->requestEndpoint($definition, $endpoint, $definition['apiSource']['authHeaders'], [
            'id' => $id,
            $definition['primaryKey'] => $id,
        ], $requestPayload);
        $saved = $this->extractWriteResponseRecord($definition, $response['body'], 'updateResponse', $record);

        $this->transactions->log($definition['entityCode'] . '.api.update', 'Registro alterado em API externa.', before: is_array($payload['record'] ?? null) ? $payload['record'] : $record, after: $saved, metadata: [
            'entityCode' => $definition['entityCode'],
            'endpoint' => $endpoint['url'],
            'httpStatus' => $response['status'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $saved;
    }

    private function delete(array $definition, array $payload): array
    {
        $endpoint = is_array($definition['apiSource']['deleteEndpoint'] ?? null) ? $definition['apiSource']['deleteEndpoint'] : null;
        if (!$endpoint) {
            throw new RuntimeHttpException('ENTITY_API_DELETE_UNAVAILABLE', 'A entidade API nao possui operacao de exclusao configurada.', 422, [
                'entityCode' => $definition['entityCode'],
            ]);
        }

        $id = $this->extractRecordId($definition, $payload);
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [$definition['primaryKey'] => $id];
        $requestPayload = $this->buildWritePayload($definition, $record, true);
        $startedAt = microtime(true);
        $response = $this->requestEndpoint($definition, $endpoint, $definition['apiSource']['authHeaders'], [
            'id' => $id,
            $definition['primaryKey'] => $id,
        ], $requestPayload);

        $this->transactions->log($definition['entityCode'] . '.api.delete', 'Registro excluido em API externa.', before: $record, metadata: [
            'entityCode' => $definition['entityCode'],
            'endpoint' => $endpoint['url'],
            'httpStatus' => $response['status'],
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return [
            'ok' => true,
            'deleted' => true,
            'id' => $id,
        ];
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
        if (empty($apiSource['listEndpoint']['url']) || empty($apiSource['listResponse']['itemsPath'])) {
            throw new RuntimeHttpException('ENTITY_API_NOT_CONFIGURED', 'Entidade API sem configuracao minima de consulta.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        $fields = [];
        $primaryKey = '';
        foreach ($entity->getFields() as $field) {
            $options = $field->getOptions();
            $apiField = is_array($options['api'] ?? null) ? $options['api'] : [];
            $jsonPath = trim((string) ($apiField['jsonPath'] ?? ''));
            if ($jsonPath === '') {
                $jsonPath = $field->getCode();
            }
            $fields[$field->getCode()] = [
                'code' => $field->getCode(),
                'label' => $field->getLabel(),
                'dataType' => $field->getDataType(),
                'primaryKey' => $field->isPrimaryKey(),
                'jsonPath' => $jsonPath,
                'writePath' => trim((string) ($apiField['writePath'] ?? $jsonPath)),
                'readonly' => ($options['readonly'] ?? false) === true || ($options['writable'] ?? true) === false,
                'lookupResolver' => $this->normalizeLookupResolver($apiField['lookupResolver'] ?? null),
            ];
            if ($field->isPrimaryKey()) {
                $primaryKey = $field->getCode();
            }
        }

        if ($primaryKey === '') {
            throw new RuntimeHttpException('ENTITY_API_PRIMARY_KEY_REQUIRED', 'Entidade API precisa definir um campo chave primaria para consulta de detalhe.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        return [
            'entityCode' => $entityCode,
            'primaryKey' => $primaryKey,
            'apiSource' => $apiSource,
            'fields' => $fields,
        ];
    }

    private function requestEndpoint(array $definition, array $endpoint, array $authHeaders, array $tokens = [], mixed $requestData = null): array
    {
        $url = $this->replaceTokens((string) ($endpoint['url'] ?? ''), $tokens);
        $headers = $this->mergeHeaders($authHeaders, is_array($endpoint['headers'] ?? null) ? $endpoint['headers'] : []);
        $query = is_array($endpoint['queryParams'] ?? null) ? $endpoint['queryParams'] : [];
        if ($tokens) {
            foreach ($tokens as $key => $value) {
                $query[$key] = $query[$key] ?? $value;
            }
        }
        if ($query) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }
        $method = strtoupper((string) ($endpoint['method'] ?? 'GET'));
        $bodyTemplate = $endpoint['bodyTemplate'] ?? null;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && is_array($bodyTemplate)) {
            foreach ($tokens as $key => $value) {
                $bodyTemplate[$key] = $bodyTemplate[$key] ?? $value;
            }
        }
        $payload = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $payloadValue = $this->mergeRequestPayload($bodyTemplate, $requestData);
            $payload = $payloadValue;
        }

        return $this->externalJsonClient->request(
            $url,
            $method,
            $headers,
            $payload,
            max(1, (int) ($definition['apiSource']['timeoutSeconds'] ?? 20)),
            ['entityCode' => $definition['entityCode']]
        );
    }

    private function mergeHeaders(array $authHeaders, array $endpointHeaders): array
    {
        $headers = [];
        foreach ([$authHeaders, $endpointHeaders] as $group) {
            foreach ($group as $key => $value) {
                $headers[(string) $key] = (string) $value;
            }
        }

        return $headers;
    }

    private function extractItems(array $body, string $itemsPath): array
    {
        $items = $this->extractByPath($body, $itemsPath);
        if (!is_array($items)) {
            throw new RuntimeHttpException('ENTITY_API_ITEMS_NOT_ARRAY', 'A API externa nao retornou um array no itemsPath configurado.', 422, [
                'itemsPath' => $itemsPath,
            ]);
        }

        return array_values($items);
    }

    private function extractByPath(mixed $value, string $path): mixed
    {
        if ($path === '' || $path === '$') {
            return $value;
        }

        return array_reduce(explode('.', $path), static function (mixed $current, string $part): mixed {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }

            return $current[$part];
        }, $value);
    }

    private function mapItem(array $definition, array $item): array
    {
        $mapped = [];
        foreach ($definition['fields'] as $field) {
            $mapped[$field['code']] = $this->extractByPath($item, (string) $field['jsonPath']);
        }

        return $mapped;
    }

    private function enrichRows(array $definition, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $groups = [];
        foreach ($definition['fields'] as $field) {
            $resolver = is_array($field['lookupResolver'] ?? null) ? $field['lookupResolver'] : null;
            if (!$resolver) {
                continue;
            }
            $groupKey = json_encode([
                'operationCode' => $resolver['operationCode'],
                'sourceField' => $resolver['sourceField'],
                'mode' => $resolver['mode'],
                'requestParam' => $resolver['requestParam'],
                'responseItemsPath' => $resolver['responseItemsPath'],
                'responseItemPath' => $resolver['responseItemPath'],
                'matchField' => $resolver['matchField'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($groupKey) || $groupKey === '') {
                continue;
            }
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'resolver' => $resolver,
                    'fields' => [],
                ];
            }
            $groups[$groupKey]['fields'][] = $field;
        }

        if ($groups === []) {
            return $rows;
        }

        $resolvedByGroup = [];
        foreach ($groups as $groupKey => $group) {
            $sourceField = (string) $group['resolver']['sourceField'];
            $values = [];
            foreach ($rows as $row) {
                $value = $row[$sourceField] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $values[(string) $value] = $value;
            }
            if ($values === []) {
                continue;
            }
            $resolvedByGroup[$groupKey] = $this->resolveLookupItems($definition, $group['resolver'], array_values($values));
        }

        if ($resolvedByGroup === []) {
            return $rows;
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($groups as $groupKey => $group) {
                $sourceValue = $row[$group['resolver']['sourceField']] ?? null;
                if ($sourceValue === null || $sourceValue === '') {
                    continue;
                }
                $resolvedItem = $resolvedByGroup[$groupKey][(string) $sourceValue] ?? null;
                if (!is_array($resolvedItem)) {
                    continue;
                }
                foreach ($group['fields'] as $field) {
                    $valuePath = (string) ($field['lookupResolver']['valuePath'] ?? '$');
                    $rows[$rowIndex][$field['code']] = $this->extractByPath($resolvedItem, $valuePath);
                }
            }
        }

        return $rows;
    }

    private function resolveLookupItems(array $definition, array $resolver, array $values): array
    {
        $operation = $this->findOperation($definition, (string) $resolver['operationCode']);
        $resolved = [];
        if ((string) $resolver['mode'] === 'per_value') {
            foreach ($values as $value) {
                $response = $this->requestEndpoint($definition, $operation, $definition['apiSource']['authHeaders'], [
                    (string) $resolver['requestParam'] => $value,
                ]);
                $item = $this->extractByPath($response['body'], (string) $resolver['responseItemPath']);
                $resolved[(string) $value] = is_array($item) ? $item : null;
            }

            return $resolved;
        }

        $response = $this->requestEndpoint($definition, $operation, $definition['apiSource']['authHeaders'], [], [
            (string) $resolver['requestParam'] => array_values($values),
        ]);
        $items = $this->extractByPath($response['body'], (string) $resolver['responseItemsPath']);
        if (!is_array($items)) {
            throw new RuntimeHttpException('ENTITY_API_LOOKUP_ITEMS_INVALID', 'A operacao de lookup nao retornou um array valido.', 422, [
                'entityCode' => $definition['entityCode'],
                'operationCode' => $resolver['operationCode'],
                'responseItemsPath' => $resolver['responseItemsPath'],
            ]);
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $matchValue = $this->extractByPath($item, (string) $resolver['matchField']);
            if ($matchValue === null || $matchValue === '') {
                continue;
            }
            $resolved[(string) $matchValue] = $item;
        }

        return $resolved;
    }

    private function findOperation(array $definition, string $operationCode): array
    {
        foreach ($definition['apiSource']['operations'] ?? [] as $operation) {
            if (is_array($operation) && (string) ($operation['code'] ?? '') === $operationCode) {
                return [
                    'url' => (string) ($operation['path'] ?? ''),
                    'method' => strtoupper((string) ($operation['method'] ?? 'GET')),
                    'headers' => is_array($operation['headers'] ?? null) ? $operation['headers'] : [],
                    'queryParams' => is_array($operation['queryParams'] ?? null) ? $operation['queryParams'] : [],
                    'bodyTemplate' => $operation['bodyTemplate'] ?? null,
                ];
            }
        }

        throw new RuntimeHttpException('ENTITY_API_LOOKUP_OPERATION_NOT_FOUND', 'Operacao de lookup nao cadastrada para a entidade API.', 422, [
            'entityCode' => $definition['entityCode'],
            'operationCode' => $operationCode,
        ]);
    }

    private function normalizeLookupResolver(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $operationCode = trim((string) ($value['operationCode'] ?? ''));
        $sourceField = trim((string) ($value['sourceField'] ?? ''));
        $requestParam = trim((string) ($value['requestParam'] ?? ''));
        $mode = strtolower(trim((string) ($value['mode'] ?? 'batch')));
        $valuePath = trim((string) ($value['valuePath'] ?? '$'));
        $responseItemsPath = trim((string) ($value['responseItemsPath'] ?? ($value['itemsPath'] ?? '$')));
        $responseItemPath = trim((string) ($value['responseItemPath'] ?? ($value['itemPath'] ?? '$')));
        $matchField = trim((string) ($value['matchField'] ?? ''));
        if ($operationCode === '' || $sourceField === '' || $requestParam === '') {
            return null;
        }
        if (!in_array($mode, ['batch', 'per_value'], true)) {
            $mode = 'batch';
        }
        if ($mode === 'batch' && $matchField === '') {
            return null;
        }

        return [
            'operationCode' => $operationCode,
            'sourceField' => $sourceField,
            'requestParam' => $requestParam,
            'mode' => $mode,
            'valuePath' => $valuePath !== '' ? $valuePath : '$',
            'responseItemsPath' => $responseItemsPath !== '' ? $responseItemsPath : '$',
            'responseItemPath' => $responseItemPath !== '' ? $responseItemPath : '$',
            'matchField' => $matchField,
        ];
    }

    private function applySort(array $rows, mixed $sortConfig): array
    {
        $sorts = is_array($sortConfig) ? $sortConfig : [];
        if (!$sorts) {
            return $rows;
        }

        usort($rows, static function (array $left, array $right) use ($sorts): int {
            foreach ($sorts as $sort) {
                if (!is_array($sort)) {
                    continue;
                }
                $field = (string) ($sort['field'] ?? '');
                if ($field === '') {
                    continue;
                }
                $dir = strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? -1 : 1;
                $a = $left[$field] ?? null;
                $b = $right[$field] ?? null;
                if ($a == $b) {
                    continue;
                }
                return ($a <=> $b) * $dir;
            }

            return 0;
        });

        return $rows;
    }

    private function applyFilter(array $rows, mixed $filter): array
    {
        if (!is_array($filter)) {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => $this->matchesFilter($row, $filter)));
    }

    private function matchesFilter(array $row, array $filter): bool
    {
        if (is_array($filter['filters'] ?? null)) {
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? 'or' : 'and';
            $results = array_map(fn (array $item): bool => $this->matchesFilter($row, $item), array_values($filter['filters']));
            return $logic === 'or' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }

        $field = (string) ($filter['field'] ?? '');
        $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
        $value = $filter['value'] ?? null;
        $current = $row[$field] ?? null;

        return match ($operator) {
            'contains' => str_contains(mb_strtolower((string) $current), mb_strtolower((string) $value)),
            'startswith' => str_starts_with(mb_strtolower((string) $current), mb_strtolower((string) $value)),
            'neq' => $current != $value,
            'gt' => $current > $value,
            'gte' => $current >= $value,
            'lt' => $current < $value,
            'lte' => $current <= $value,
            default => $current == $value,
        };
    }

    private function extractRecordId(array $definition, array $payload): string|int
    {
        foreach (['id', $definition['primaryKey']] as $field) {
            $value = $payload[$field] ?? (is_array($payload['record'] ?? null) ? ($payload['record'][$field] ?? null) : null);
            if (is_scalar($value) && $value !== '') {
                return is_int($value) ? $value : (string) $value;
            }
        }

        throw new RuntimeHttpException('ENTITY_API_ID_REQUIRED', 'Informe o identificador do registro para consultar o detalhe.', 422, [
            'entityCode' => $definition['entityCode'],
            'primaryKey' => $definition['primaryKey'],
        ]);
    }

    private function replaceTokens(string $value, array $tokens): string
    {
        return preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', static function (array $matches) use ($tokens): string {
            $key = (string) ($matches[1] ?? '');
            return isset($tokens[$key]) ? rawurlencode((string) $tokens[$key]) : '';
        }, $value) ?? $value;
    }

    private function extractSubmittedRecord(array $payload): array
    {
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : $payload;
        unset($record['_runtime'], $record['_customCode'], $record['operation'], $record['entityCode'], $record['actionId'], $record['programId']);

        return is_array($record) ? $record : [];
    }

    private function buildWritePayload(array $definition, array $record, bool $includeReadonly): array
    {
        $payload = [];
        foreach ($definition['fields'] as $field) {
            if (!$includeReadonly && $field['readonly']) {
                continue;
            }
            $code = (string) $field['code'];
            if (!array_key_exists($code, $record)) {
                continue;
            }
            $writePath = trim((string) ($field['writePath'] ?? ''));
            if ($writePath === '') {
                $writePath = $code;
            }
            $this->setByPath($payload, $writePath, $record[$code]);
        }

        return $payload;
    }

    private function setByPath(array &$target, string $path, mixed $value): void
    {
        if ($path === '' || $path === '$') {
            return;
        }
        $parts = explode('.', $path);
        $current = &$target;
        foreach ($parts as $index => $part) {
            if ($index === count($parts) - 1) {
                $current[$part] = $value;
                return;
            }
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
    }

    private function mergeRequestPayload(mixed $template, mixed $requestData): mixed
    {
        if ($requestData === null) {
            return $template;
        }
        if ($template === null) {
            return $requestData;
        }
        if (is_array($template) && is_array($requestData)) {
            return array_replace_recursive($template, $requestData);
        }
        if (is_scalar($template)) {
            return $template;
        }

        return $requestData;
    }

    private function extractWriteResponseRecord(array $definition, array $body, string $responseKey, array $fallback): array
    {
        $responseConfig = is_array($definition['apiSource'][$responseKey] ?? null) ? $definition['apiSource'][$responseKey] : [];
        $itemPath = trim((string) ($responseConfig['itemPath'] ?? ''));
        if ($itemPath === '') {
            return $fallback;
        }
        $item = $this->extractByPath($body, $itemPath);
        if (!is_array($item)) {
            return $fallback;
        }

        return $this->mapItem($definition, $item);
    }
}
