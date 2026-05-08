<?php

namespace App\Runtime;

class RuntimeJobEnqueueService
{
    public function __construct(
        private readonly RuntimeAsyncJobService $asyncJobs,
    ) {
    }

    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $jobs = $this->normalizeJobs($config['jobs'] ?? $config['job'] ?? []);
        if (!$jobs) {
            throw new RuntimeHttpException('RUNTIME_JOB_CONFIG_REQUIRED', 'Endpoint de job sem configuracao.', 422, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
            ]);
        }

        $scheduled = 0;
        foreach ($jobs as $job) {
            if (($job['enabled'] ?? true) === false) {
                continue;
            }
            if (($job['mode'] ?? 'async') !== 'async') {
                throw new RuntimeHttpException('RUNTIME_JOB_MODE_NOT_SUPPORTED', 'Modo de job nao suportado neste endpoint.', 422, [
                    'mode' => $job['mode'] ?? null,
                ]);
            }
            if (!$this->matchesCondition($payload, $job['when'] ?? null)) {
                continue;
            }

            $this->assertRequiredPayload($payload, $job);
            $this->asyncJobs->schedule((string) ($job['type'] ?? ''), $this->buildJobPayload($payload, $job), [
                'screenId' => $screenId,
                'programId' => $payload['programId'] ?? $config['programId'] ?? null,
                'entityCode' => $payload['entityCode'] ?? $config['entityCode'] ?? null,
                'recordId' => $this->recordId($payload),
                'actionId' => $payload['actionId'] ?? $config['actionId'] ?? $endpointId,
                'message' => $job['queuedMessage'] ?? $job['message'] ?? null,
                'jobConfigId' => $job['id'] ?? null,
                'jobConfigSource' => 'endpoint',
            ]);
            ++$scheduled;
        }

        return [
            'ok' => true,
            'queued' => $scheduled,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeJobs(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        if (isset($raw['type'])) {
            $raw = [$raw];
        } elseif (!array_is_list($raw)) {
            $items = [];
            foreach ($raw as $id => $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (is_string($id) && !isset($item['id'])) {
                    $item['id'] = $id;
                }
                $items[] = $item;
            }
            $raw = $items;
        }

        return array_values(array_filter($raw, 'is_array'));
    }

    private function assertRequiredPayload(array $payload, array $job): void
    {
        $required = $this->normalizeRequired($job['required'] ?? []);
        $messages = [];
        foreach ($required as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $value = $this->resolvePath($payload, $path);
            if ($value !== null && trim((string) $value) !== '') {
                continue;
            }
            $messages[] = [
                'field' => (string) ($item['field'] ?? ''),
                'type' => 'error',
                'message' => (string) ($item['message'] ?? 'Informe os dados obrigatorios para executar a acao.'),
            ];
        }

        if ($messages) {
            throw new RuntimeValidationException('RUNTIME_JOB_REQUIRED_PAYLOAD', 'Existem inconsistencias na acao.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'messages' => $messages,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeRequired(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        if (isset($raw['path'])) {
            return [$raw];
        }

        $items = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $items[] = ['path' => $item];
            } elseif (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function matchesCondition(array $payload, mixed $condition): bool
    {
        if ($condition === null || $condition === [] || $condition === true) {
            return true;
        }
        if (!is_array($condition)) {
            return false;
        }
        if (array_is_list($condition)) {
            foreach ($condition as $item) {
                if (!$this->matchesCondition($payload, $item)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $item) {
                if (!$this->matchesCondition($payload, $item)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $item) {
                if ($this->matchesCondition($payload, $item)) {
                    return true;
                }
            }

            return false;
        }

        $path = (string) ($condition['path'] ?? '');
        if ($path === '' && isset($condition['field'])) {
            $source = (string) ($condition['source'] ?? 'values');
            $path = $source . '.' . (string) $condition['field'];
        }

        return $path !== '' && $this->compare($this->resolvePath($payload, $path), (string) ($condition['operator'] ?? 'isNotEmpty'), $condition['value'] ?? null);
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match (strtolower($operator)) {
            'isempty', 'empty' => $actual === null || trim((string) $actual) === '',
            'isnotempty', 'notempty' => $actual !== null && trim((string) $actual) !== '',
            'eq', 'equals' => $actual == $expected,
            'neq', 'notequals', 'not_equals' => $actual != $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJobPayload(array $payload, array $job): array
    {
        $mapping = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $result = [];
        foreach ($mapping as $target => $path) {
            $target = (string) $target;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $target)) {
                continue;
            }
            $result[$target] = is_string($path) ? $this->resolvePath($payload, $path) : $path;
        }

        return $result ?: [
            'recordId' => $this->recordId($payload),
        ];
    }

    private function recordId(array $payload): null|string|int
    {
        foreach (['id', 'recordId', 'clienteId'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_scalar($value) && $value !== '') {
                return is_int($value) ? $value : (string) $value;
            }
        }
        foreach (['values', 'record'] as $group) {
            if (!is_array($payload[$group] ?? null)) {
                continue;
            }
            foreach (['id', 'recordId', 'clienteId'] as $field) {
                $value = $payload[$group][$field] ?? null;
                if (is_scalar($value) && $value !== '') {
                    return is_int($value) ? $value : (string) $value;
                }
            }
        }

        return null;
    }

    private function resolvePath(array $payload, string $path): mixed
    {
        $segments = explode('.', $path);
        $source = array_shift($segments);
        $sources = [
            'payload' => $payload,
            'values' => is_array($payload['values'] ?? null) ? $payload['values'] : [],
            'record' => is_array($payload['record'] ?? null) ? $payload['record'] : [],
            'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
            'runtime' => is_array($payload['_runtime'] ?? null) ? $payload['_runtime'] : [],
            'endpoint' => is_array($payload['_runtimeEndpoint'] ?? null) ? $payload['_runtimeEndpoint'] : [],
        ];
        if (!is_string($source) || !array_key_exists($source, $sources)) {
            return null;
        }

        $value = $sources[$source];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_scalar($value) || $value === null || is_array($value) ? $value : null;
    }
}
