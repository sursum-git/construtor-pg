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
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
    }

    public function begin(string $screenId, string $endpointId, string $handler, array $payload): RuntimeTransaction
    {
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $runtime = is_array($payload['_runtime'] ?? null) ? $payload['_runtime'] : [];
        $entityCode = $this->inferEntityCode($handler, $payload);
        $traceability = $this->buildTraceability($screenId, $endpointId, $handler, $payload, $entityCode);
        $impersonation = $this->currentImpersonation();

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
            ->setRequestContext($this->compactPayload($payload, $entityCode, $traceability, $impersonation));

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
            'traceability' => $traceability,
            'impersonation' => $impersonation,
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

    /**
     * @param array<string, mixed> $context
     */
    public function beginOperational(array $context): RuntimeTransaction
    {
        $tenantId = (string) ($context['tenantId'] ?? $this->permissions->getTenantId());
        $sessionId = (string) ($context['sessionId'] ?? $this->permissions->getSessionId());
        $screenId = (string) ($context['screenId'] ?? 'system.eventbus');
        $endpointId = (string) ($context['endpointId'] ?? 'eventbus.worker');
        $operation = (string) ($context['operation'] ?? 'runtime.event.process');
        $entityCode = isset($context['entityCode']) && $context['entityCode'] !== '' ? (string) $context['entityCode'] : null;
        $impersonation = is_array($context['impersonation'] ?? null) ? $context['impersonation'] : $this->currentImpersonation();
        if ($impersonation !== []) {
            $context['impersonation'] = $impersonation;
        }

        $transaction = (new RuntimeTransaction())
            ->setTenantId($tenantId)
            ->setSessionId($sessionId)
            ->setScreenId($screenId)
            ->setProgramId(isset($context['programId']) ? (string) $context['programId'] : null)
            ->setEntityCode($entityCode)
            ->setRecordId($this->normalizeRecordId($context['recordId'] ?? null))
            ->setEndpointId($endpointId)
            ->setActionId(isset($context['actionId']) ? (string) $context['actionId'] : $endpointId)
            ->setOperation($operation)
            ->setRequestContext($this->withImpersonation($this->redactSensitiveValues($context), $impersonation));

        $this->entityManager->persist($transaction);
        $this->current = $transaction;
        $this->executionContext->open(array_merge($context, [
            'tenantId' => $tenantId,
            'sessionId' => $sessionId,
            'screenId' => $screenId,
            'endpointId' => $endpointId,
            'operation' => $operation,
            'source' => (string) ($context['source'] ?? 'eventbus'),
        ]), $transaction);
        $this->log('runtime.operational_transaction.started', 'Transacao operacional iniciada.', metadata: [
            'operation' => $operation,
            'source' => $context['source'] ?? 'eventbus',
        ]);
        $this->entityManager->flush();

        return $transaction;
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

    public function clear(): void
    {
        $this->current = null;
        $this->executionContext->clear();
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
            ->setMetadata($this->mergeTraceabilityMetadata($metadata));

        $this->entityManager->persist($log);
    }

    private function inferEntityCode(string $handler, array $payload): ?string
    {
        if (!empty($payload['entityCode'])) {
            return (string) $payload['entityCode'];
        }
        if (in_array($handler, ['entity.crud', 'entity.api.readonly', 'entity.api.crud', 'entity.api.odoo.readonly'], true) && !empty($payload['_runtimeEndpoint']['entityCode'])) {
            return (string) $payload['_runtimeEndpoint']['entityCode'];
        }
        if (str_starts_with($handler, 'cliente.') || str_starts_with($handler, 'entity.cliente.')) {
            return 'cliente';
        }
        return null;
    }

    private function compactPayload(array $payload, ?string $entityCode, array $traceability, array $impersonation): array
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

        $payload['traceability'] = $traceability;
        if ($impersonation !== []) {
            $payload['impersonation'] = $impersonation;
        }

        return $payload;
    }

    private function mergeTraceabilityMetadata(array $metadata): array
    {
        $requestContext = $this->current?->getRequestContext() ?? [];
        $traceability = is_array($requestContext['traceability'] ?? null) ? $requestContext['traceability'] : [];
        foreach ($traceability as $key => $value) {
            if (!array_key_exists($key, $metadata)) {
                $metadata[$key] = $value;
            }
        }
        $impersonation = is_array($requestContext['impersonation'] ?? null) ? $requestContext['impersonation'] : [];
        if ($impersonation !== [] && !array_key_exists('impersonation', $metadata)) {
            $metadata['impersonation'] = $impersonation;
            $metadata['effectiveUserId'] = $impersonation['targetUserId'] ?? $this->permissions->getUserId();
            $metadata['originalUserId'] = $impersonation['actorUserId'] ?? null;
            $metadata['impersonationReason'] = $impersonation['reason'] ?? null;
        }

        return $metadata;
    }

    private function currentImpersonation(): array
    {
        $user = $this->permissions->getCurrentUserPayload();
        $impersonation = is_array($user['impersonation'] ?? null) ? $user['impersonation'] : [];
        if (($impersonation['enabled'] ?? false) === true) {
            return $impersonation;
        }

        return [];
    }

    private function withImpersonation(mixed $context, array $impersonation): array
    {
        $context = is_array($context) ? $context : [];
        if ($impersonation !== []) {
            $context['impersonation'] = $impersonation;
        }

        return $context;
    }

    private function buildTraceability(string $screenId, string $endpointId, string $handler, array $payload, ?string $entityCode): array
    {
        $runtimeEndpoint = is_array($payload['_runtimeEndpoint'] ?? null) ? $payload['_runtimeEndpoint'] : [];
        $traceability = is_array($runtimeEndpoint['traceability'] ?? null) ? $runtimeEndpoint['traceability'] : [];
        $environment = $this->environmentIdentity->resolve();
        $definition = null;
        if ($entityCode !== null) {
            try {
                $definition = $this->definitions->resolve($entityCode);
            } catch (\Throwable) {
                $definition = null;
            }
        }

        $schemaFingerprint = (string) ($traceability['schemaFingerprint'] ?? '');
        if ($schemaFingerprint === '' && is_array($definition)) {
            $schemaFingerprint = $this->entitySchemaFingerprint($definition);
        }

        return array_filter([
            'programCode' => $traceability['programCode'] ?? ($payload['programId'] ?? $runtimeEndpoint['programId'] ?? null),
            'programVersion' => $traceability['programVersion'] ?? null,
            'builderProgramVersionId' => $traceability['builderProgramVersionId'] ?? null,
            'entityCode' => $traceability['entityCode'] ?? $entityCode,
            'builderEntityVersionId' => $traceability['builderEntityVersionId'] ?? null,
            'screenId' => $screenId,
            'screenDefinitionVersion' => $traceability['screenDefinitionVersion'] ?? null,
            'schemaFingerprint' => $schemaFingerprint !== '' ? $schemaFingerprint : null,
            'databaseIdentity' => $environment['databaseIdentity'] ?? null,
            'databaseEnvironment' => $environment['databaseEnvironment'] ?? null,
            'customizationKind' => $traceability['customizationKind'] ?? null,
            'subscriberId' => $traceability['subscriberId'] ?? $this->permissions->getTenantId(),
            'grantId' => $traceability['grantId'] ?? null,
            'requestCode' => $traceability['requestCode'] ?? null,
            'approvalId' => $traceability['approvalId'] ?? null,
            'testExecutionBundleId' => $traceability['testExecutionBundleId'] ?? null,
            'endpointId' => $endpointId,
            'handler' => $handler,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function entitySchemaFingerprint(array $definition): string
    {
        $payload = [
            'entityCode' => $definition['entityCode'] ?? null,
            'tableName' => $definition['tableName'] ?? null,
            'primaryKey' => $definition['primaryKey'] ?? null,
            'fields' => array_map(static function (array $field): array {
                return [
                    'column' => $field['column'] ?? null,
                    'dataType' => $field['dataType'] ?? null,
                    'databaseType' => $field['databaseType'] ?? null,
                    'writable' => $field['writable'] ?? null,
                    'readable' => $field['readable'] ?? null,
                ];
            }, is_array($definition['fields'] ?? null) ? $definition['fields'] : []),
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
