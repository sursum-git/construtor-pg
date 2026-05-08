<?php

namespace App\Runtime;

class RuntimeConfiguredJobScheduler
{
    public function __construct(
        private readonly RuntimeAsyncJobService $asyncJobs,
    ) {
    }

    public function scheduleAfterSuccess(RuntimeBusinessRuleContext $context): void
    {
        foreach ($this->resolveJobs($context) as $job) {
            if (!$this->shouldSchedule($context, $job)) {
                continue;
            }

            $this->asyncJobs->schedule((string) $job['type'], $this->buildPayload($context, $job), $this->buildOptions($context, $job));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveJobs(RuntimeBusinessRuleContext $context): array
    {
        $definition = $context->getDefinition();
        $payload = $context->getPayload();
        $runtimeEndpoint = is_array($payload['_runtimeEndpoint'] ?? null) ? $payload['_runtimeEndpoint'] : [];
        $jobs = array_merge(
            $this->normalizeJobs($definition['metadata']['jobs'] ?? [], 'entity'),
            $this->normalizeJobs($runtimeEndpoint['jobs'] ?? [], 'endpoint'),
        );

        $resolved = [];
        foreach ($jobs as $job) {
            $type = trim((string) ($job['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $key = trim((string) ($job['id'] ?? '')) ?: $type;
            $job['type'] = $type;
            $resolved[$key] = $job;
        }

        return array_values($resolved);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeJobs(mixed $raw, string $source): array
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

        $jobs = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['_source'] = $source;
            $jobs[] = $item;
        }

        return $jobs;
    }

    private function shouldSchedule(RuntimeBusinessRuleContext $context, array $job): bool
    {
        if (($job['enabled'] ?? true) === false) {
            return false;
        }
        if (($job['trigger'] ?? 'after_success') !== 'after_success') {
            return false;
        }
        if (($job['mode'] ?? 'async') !== 'async') {
            return false;
        }
        if (!$this->matchesList($context->getOperation(), $job['operations'] ?? null)) {
            return false;
        }
        if (!$this->matchesList($context->getActionId(), $job['actionIds'] ?? $job['actions'] ?? null)) {
            return false;
        }

        return $this->matchesCondition($context, $job['when'] ?? null);
    }

    private function matchesList(string $value, mixed $allowed): bool
    {
        if ($allowed === null || $allowed === '' || $allowed === []) {
            return true;
        }

        $items = is_array($allowed) ? $allowed : [$allowed];
        foreach ($items as $item) {
            if ((string) $item === $value) {
                return true;
            }
        }

        return false;
    }

    private function matchesCondition(RuntimeBusinessRuleContext $context, mixed $condition): bool
    {
        if ($condition === null || $condition === [] || $condition === true) {
            return true;
        }
        if (!is_array($condition)) {
            return false;
        }
        if (array_is_list($condition)) {
            foreach ($condition as $item) {
                if (!$this->matchesCondition($context, $item)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $item) {
                if (!$this->matchesCondition($context, $item)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $item) {
                if ($this->matchesCondition($context, $item)) {
                    return true;
                }
            }

            return false;
        }

        $source = (string) ($condition['source'] ?? 'after');
        $path = (string) ($condition['path'] ?? '');
        if ($path === '' && isset($condition['field'])) {
            $path = $source . '.' . (string) $condition['field'];
        }
        if ($path === '') {
            return false;
        }

        return $this->compare($this->resolvePath($context, $path), (string) ($condition['operator'] ?? 'isNotEmpty'), $condition['value'] ?? null);
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match (strtolower($operator)) {
            'isempty', 'empty' => $actual === null || trim((string) $actual) === '',
            'isnotempty', 'notempty' => $actual !== null && trim((string) $actual) !== '',
            'isemail', 'email' => is_scalar($actual) && filter_var((string) $actual, FILTER_VALIDATE_EMAIL) !== false,
            'eq', 'equals' => $actual == $expected,
            'neq', 'notequals', 'not_equals' => $actual != $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'notin', 'not_in' => is_array($expected) && !in_array($actual, $expected, true),
            'istrue', 'true' => filter_var($actual, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true,
            'isfalse', 'false' => filter_var($actual, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(RuntimeBusinessRuleContext $context, array $job): array
    {
        $mapping = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        if (!$mapping) {
            return [
                'entityCode' => $context->getEntityCode(),
                'recordId' => $this->recordId($context),
            ];
        }

        $payload = [];
        foreach ($mapping as $target => $path) {
            $target = (string) $target;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $target)) {
                continue;
            }
            $payload[$target] = is_string($path) ? $this->resolvePath($context, $path) : $path;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(RuntimeBusinessRuleContext $context, array $job): array
    {
        $definition = $context->getDefinition();
        $payload = $context->getPayload();
        $requestContext = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $runtimeEndpoint = is_array($payload['_runtimeEndpoint'] ?? null) ? $payload['_runtimeEndpoint'] : [];

        return [
            'screenId' => $payload['screenId'] ?? $requestContext['programScreenId'] ?? $definition['metadata']['screenId'] ?? '',
            'programId' => $payload['programId'] ?? $runtimeEndpoint['programId'] ?? $requestContext['programId'] ?? '',
            'entityCode' => $context->getEntityCode(),
            'recordId' => $this->recordId($context),
            'actionId' => $context->getActionId(),
            'message' => $job['queuedMessage'] ?? $job['message'] ?? null,
            'jobConfigId' => $job['id'] ?? null,
            'jobConfigSource' => $job['_source'] ?? null,
        ];
    }

    private function recordId(RuntimeBusinessRuleContext $context): null|string|int
    {
        $definition = $context->getDefinition();
        $primaryKey = (string) ($definition['primaryKey'] ?? 'id');
        foreach ([$context->getAfter(), $context->getBefore(), $context->getValues(), $context->getPayload()] as $source) {
            $value = $source[$primaryKey] ?? $source['id'] ?? $source['recordId'] ?? null;
            if (is_scalar($value) && $value !== '') {
                return is_int($value) ? $value : (string) $value;
            }
        }

        return null;
    }

    private function resolvePath(RuntimeBusinessRuleContext $context, string $path): mixed
    {
        $segments = explode('.', $path);
        $source = array_shift($segments);
        $payload = $context->getPayload();
        $sources = [
            'after' => $context->getAfter(),
            'before' => $context->getBefore(),
            'values' => $context->getValues(),
            'payload' => $payload,
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
