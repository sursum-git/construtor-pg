<?php

namespace App\Runtime;

class RuntimeSystemHandler
{
    public function __construct(
        private readonly RuntimeLockService $locks,
        private readonly RuntimeMessageService $messages,
        private readonly RuntimeSessionGuard $sessions,
        private readonly RuntimeTransactionService $transactions,
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
}
