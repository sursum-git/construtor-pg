<?php

namespace App\Runtime;

class RuntimeSystemHandler
{
    public function __construct(
        private readonly RuntimeLockService $locks,
        private readonly RuntimeMessageService $messages,
        private readonly RuntimeSessionGuard $sessions,
        private readonly RuntimeTransactionService $transactions,
        private readonly StructuralIntegrityService $integrity,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
        private readonly PermissionResolver $permissions,
        private readonly \Doctrine\ORM\EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(string $operation, string $screenId, array $payload): array
    {
        return match ($operation) {
            'lockAcquire' => $this->locks->acquire($screenId, $payload),
            'lockHeartbeat' => $this->locks->heartbeat($payload),
            'lockRelease' => $this->locks->release($payload),
            'messagesPoll' => $this->messages->poll(),
            'messagesAck' => $this->messages->acknowledge($payload),
            'adminForceLogout' => $this->forceLogout($payload),
            'adminIntegrityResign' => $this->resignIntegrity($payload),
            default => throw new RuntimeHttpException('RUNTIME_SYSTEM_OPERATION_NOT_FOUND', 'Operacao runtime nao encontrada.', 404, [
                'operation' => $operation,
            ]),
        };
    }

    private function forceLogout(array $payload): array
    {
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
        $targetUserId = trim((string) ($payload['targetUserId'] ?? $payload['userId'] ?? $record['user_id'] ?? $values['user_id'] ?? ''));
        $targetSessionId = trim((string) ($payload['targetSessionId'] ?? $payload['sessionId'] ?? $record['session_id'] ?? $values['session_id'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? 'Sessao encerrada pelo administrador.'));

        if ($targetUserId === '' && $targetSessionId === '') {
            throw new RuntimeHttpException('TARGET_USER_REQUIRED', 'Informe o usuario ou sessao que sera derrubado.', 422);
        }
        if ($targetUserId === '') {
            $targetUserId = 'demo';
        }

        $revokedSessions = $this->sessions->revokeTarget($targetUserId, $targetSessionId ?: null, $reason);
        $revokedRememberTokens = $this->sessions->revokeRememberTokensForTarget($targetUserId, $targetSessionId ?: null, $reason);
        $releasedLocks = $this->locks->releaseUserLocks($targetUserId, $targetSessionId ?: null, 'revoked');
        $this->messages->createForceLogout($targetUserId, $targetSessionId ?: null, $reason);
        $this->transactions->log('session.revoked', 'Usuario derrubado pelo runtime administrativo.', metadata: [
            'targetUserId' => $targetUserId,
            'targetSessionId' => $targetSessionId ?: null,
            'sessions' => count($revokedSessions),
            'rememberTokens' => $revokedRememberTokens,
            'releasedLocks' => $releasedLocks,
        ]);

        return [
            'ok' => true,
            'revokedSessions' => count($revokedSessions),
            'revokedRememberTokens' => $revokedRememberTokens,
            'releasedLocks' => $releasedLocks,
            'phpSessionKillRequested' => count($revokedSessions) > 0,
        ];
    }

    private function resignIntegrity(array $payload): array
    {
        $environment = strtolower(trim((string) (($this->environmentIdentity->resolve()['databaseEnvironment'] ?? 'dev'))));
        if (in_array($environment, ['prod', 'production'], true)) {
            throw new RuntimeHttpException('PROGRAM_MAINTENANCE_ENVIRONMENT_BLOCKED', 'A reassinatura estrutural nao esta autorizada neste ambiente.', 422, [
                'databaseEnvironment' => $environment,
            ]);
        }

        if (!$this->permissions->hasPermission('admin.write')) {
            throw new RuntimeHttpException('ADMIN_FORBIDDEN', 'Voce nao possui permissao para reassinar a estrutura.', 403);
        }

        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
        $tableName = strtolower(trim((string) ($payload['tableName'] ?? $record['table_name'] ?? $values['table_name'] ?? '')));
        $recordId = (int) ($payload['recordId'] ?? $record['record_id'] ?? $values['record_id'] ?? 0);
        if ($tableName === '' || $recordId <= 0) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_TARGET_REQUIRED', 'Informe a tabela e o registro que serao reassinados.', 422);
        }

        $reason = trim((string) ($payload['reason'] ?? 'Reassinatura manual via runtime admin.integridade'));
        $this->integrity->resignTarget($tableName, $recordId, [
            'source' => 'runtime.admin.integridade',
            'triggeredBy' => $this->permissions->getUserId(),
            'tenantId' => $this->permissions->getTenantId(),
            'sessionId' => $this->permissions->getSessionId(),
            'reason' => $reason,
        ]);
        $this->entityManager->flush();
        $status = $this->integrity->verifyTarget($tableName, $recordId);
        $this->transactions->log('integrity.resigned', 'Registro estrutural reassinado pelo runtime administrativo.', metadata: [
            'tableName' => $tableName,
            'recordId' => $recordId,
            'reason' => $reason,
            'statusAfter' => $status['status'] ?? null,
        ]);
        $this->entityManager->flush();

        return [
            'integrity' => $status,
        ];
    }
}
