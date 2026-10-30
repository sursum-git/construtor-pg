<?php
namespace App\Runtime;

/** Closed context forwarding for registered external operations. No arbitrary filters go upstream. */
final class ApiRequestContext
{
    public static function resolve(array $definition, array $endpoint, array $payload): array
    {
        $fields = $endpoint['requestContextFields'] ?? [];
        if ($fields === []) { return []; }
        $invalid = static fn () => new RuntimeHttpException('ENTITY_API_CONTEXT_INVALID', 'Contexto obrigatorio, invalido ou conflitante para a API externa.', 422);
        if (!is_array($fields) || !array_is_list($fields) || count($fields) > 8 || count(array_unique($fields, SORT_REGULAR)) !== count($fields)
            || strtoupper((string) ($endpoint['method'] ?? 'GET')) !== 'POST') { throw $invalid(); }
        foreach ($fields as $field) {
            if (!is_string($field) || !preg_match('/\A[a-z][a-z0-9_]*\z/', $field) || ($definition['fields'][$field]['dataType'] ?? null) !== 'integer') { throw $invalid(); }
        }
        $rawContext = $payload['context'] ?? [];
        if (!is_array($rawContext) || ($rawContext !== [] && array_is_list($rawContext)) || array_diff(array_keys($rawContext), $fields)) { throw $invalid(); }
        $result = [];
        $assign = static function (string $field, mixed $value) use (&$result, $invalid): void {
            if (!(is_int($value) || is_string($value)) || !preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $value) || (int) $value > 2147483647) { throw $invalid(); }
            $value = (int) $value;
            if (array_key_exists($field, $result) && $result[$field] !== $value) { throw $invalid(); }
            $result[$field] = $value;
        };
        foreach ($rawContext as $field => $value) { $assign($field, $value); }
        $visit = static function (mixed $filter, bool $conjunctive = true) use (&$visit, $fields, $assign, $invalid): void {
            if (!is_array($filter)) { return; }
            if (array_key_exists('filters', $filter)) {
                $and = $conjunctive && strtolower((string) ($filter['logic'] ?? 'and')) === 'and';
                if (!is_array($filter['filters'])) { throw $invalid(); }
                foreach ($filter['filters'] as $child) { $visit($child, $and); }
                return;
            }
            $field = $filter['field'] ?? null;
            if (!in_array($field, $fields, true)) { return; }
            if (!$conjunctive || ($filter['operator'] ?? 'eq') !== 'eq') { throw $invalid(); }
            $assign($field, $filter['value'] ?? null);
        };
        $visit($payload['filter'] ?? null);
        foreach ($payload['filters'] ?? [] as $filter) { $visit($filter); }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $result)) { throw $invalid(); }
            if (array_key_exists($field, $endpoint['bodyTemplate'] ?? []) && $endpoint['bodyTemplate'][$field] !== $result[$field]) { throw $invalid(); }
        }
        return $result;
    }

    public static function assertRows(array $rows, array $context): void
    {
        foreach ($rows as $row) {
            foreach ($context as $field => $expected) {
                $actual = $row[$field] ?? null;
                if (!(is_int($actual) || is_string($actual)) || !preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $actual) || (int) $actual !== $expected) {
                    throw new RuntimeHttpException('ENTITY_API_CONTEXT_RESPONSE_INVALID', 'A API retornou dados fora do contexto solicitado.', 502);
                }
            }
        }
    }
}
