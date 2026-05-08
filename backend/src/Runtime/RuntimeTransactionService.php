<?php

namespace App\Runtime;

use App\Entity\RuntimeTransaction;
use App\Entity\RuntimeTransactionLog;
use Doctrine\ORM\EntityManagerInterface;

class RuntimeTransactionService
{
    private ?RuntimeTransaction $current = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeExecutionContext $executionContext,
        private readonly RuntimeEntityDefinitionResolver $definitions,
    ) {
    }

    public function begin(string $screenId, string $endpointId, string $handler, array $payload): RuntimeTransaction
    {
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $runtime = is_array($payload['_runtime'] ?? null) ? $payload['_runtime'] : [];
        $entityCode = $this->inferEntityCode($handler, $payload);

        $transaction = (new RuntimeTransaction())
            ->setTenantId($this->permissions->getTenantId())
            ->setSessionId($this->permissions->getSessionId())
            ->setScreenId($screenId)
            ->setProgramId((string) ($payload['programId'] ?? $context['programId'] ?? ''))
            ->setEntityCode($entityCode)
            ->setRecordId($this->extractRecordId($payload))
            ->setEndpointId($endpointId)
            ->setActionId((string) ($payload['actionId'] ?? $payload['action'] ?? $endpointId))
            ->setOperation($handler)
            ->setLockToken((string) ($runtime['lockToken'] ?? $payload['lockToken'] ?? ''))
            ->setRequestContext($this->compactPayload($payload, $entityCode));

        $this->entityManager->persist($transaction);
        $this->current = $transaction;
        $this->executionContext->open([
            'tenantId' => $this->permissions->getTenantId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => $screenId,
            'programId' => (string) ($payload['programId'] ?? $context['programId'] ?? ''),
            'entityCode' => $entityCode,
            'recordId' => $this->extractRecordId($payload),
            'endpointId' => $endpointId,
            'actionId' => (string) ($payload['actionId'] ?? $payload['action'] ?? $endpointId),
            'operation' => $handler,
            'source' => 'runtime',
        ], $transaction);
        $this->log('runtime.request', 'Chamada runtime recebida.', metadata: [
            'screenId' => $screenId,
            'endpointId' => $endpointId,
            'handler' => $handler,
        ]);
        $this->entityManager->flush();
        $this->executionContext->setTransaction($transaction);

        return $transaction;
    }

    public function getCurrent(): ?RuntimeTransaction
    {
        return $this->current;
    }

    public function success(array $metadata = []): void
    {
        if (!$this->current) {
            return;
        }
        $this->log('runtime.success', 'Chamada runtime concluida.', metadata: $metadata);
        $this->current->finish('succeeded');
        $this->entityManager->flush();
    }

    public function fail(\Throwable $error): void
    {
        if (!$this->current) {
            return;
        }
        $metadata = [
            'exception' => $error::class,
            'code' => $error instanceof RuntimeHttpException ? $error->getErrorCode() : 'RUNTIME_ERROR',
        ];
        if ($error instanceof RuntimeValidationException) {
            $metadata['validation'] = $error->getValidation();
            $metadata['effects'] = $error->getEffects();
        }
        $this->log($error instanceof RuntimeValidationException ? 'runtime.validation' : 'runtime.error', $error->getMessage(), metadata: $metadata);
        $this->current->finish($error instanceof RuntimeHttpException ? 'failed' : 'error');
        $this->entityManager->flush();
    }

    public function log(
        string $eventType,
        ?string $message = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
    ): void {
        if (!$this->current) {
            return;
        }

        $log = (new RuntimeTransactionLog())
            ->setTransaction($this->current)
            ->setEventType($eventType)
            ->setMessage($message)
            ->setBeforeData($before)
            ->setAfterData($after)
            ->setDiffData($this->diff($before, $after))
            ->setMetadata($metadata);

        $this->entityManager->persist($log);
    }

    private function inferEntityCode(string $handler, array $payload): ?string
    {
        if (!empty($payload['entityCode'])) {
            return (string) $payload['entityCode'];
        }
        if ($handler === 'entity.crud' && !empty($payload['_runtimeEndpoint']['entityCode'])) {
            return (string) $payload['_runtimeEndpoint']['entityCode'];
        }
        if (str_starts_with($handler, 'cliente.') || str_starts_with($handler, 'entity.cliente.')) {
            return 'cliente';
        }
        return null;
    }

    private function compactPayload(array $payload, ?string $entityCode): array
    {
        $payload = $this->redactSensitiveValues($payload);

        if ($entityCode !== null && is_array($payload['values'] ?? null)) {
            try {
                $payload['values'] = $this->filterAllowedKeys(
                    $payload['values'],
                    $this->definitions->getAllowedFieldCodes($entityCode),
                );
            } catch (\Throwable) {
                $payload['values'] = [];
            }
        }

        return $payload;
    }

    private function extractRecordId(array $payload): null|string|int
    {
        foreach (['id', 'recordId', 'clienteId'] as $field) {
            $value = $this->normalizeRecordId($payload[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        foreach (['record', 'values'] as $group) {
            if (!is_array($payload[$group] ?? null)) {
                continue;
            }
            foreach (['id', 'recordId', 'clienteId'] as $field) {
                $value = $this->normalizeRecordId($payload[$group][$field] ?? null);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function normalizeRecordId(mixed $value): null|string|int
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return null;
        }

        return is_int($value) ? $value : (string) $value;
    }

    /**
     * @param string[] $allowedKeys
     */
    private function filterAllowedKeys(array $values, array $allowedKeys): array
    {
        $allowed = array_fill_keys($allowedKeys, true);
        $result = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (!isset($allowed[$key])) {
                continue;
            }
            if ($value !== null && !is_scalar($value)) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function redactSensitiveValues(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }
            $result[$key] = $this->redactSensitiveValues($item);
        }

        return $result;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        return (bool) preg_match('/(senha|password|token|authorization|api[_-]?key|secret|private[_-]?key)/i', $key);
    }

    private function diff(array $before, array $after): array
    {
        if (!$before && !$after) {
            return [];
        }

        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($keys as $key) {
            $left = $before[$key] ?? null;
            $right = $after[$key] ?? null;
            if ($left !== $right) {
                $diff[$key] = [
                    'before' => $left,
                    'after' => $right,
                ];
            }
        }

        return $diff;
    }
}
