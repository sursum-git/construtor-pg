<?php

namespace App\Runtime;

class RuntimeConcurrencyGuard
{
    public function assertExpectedVersion(string $entityCode, string $actionId, ?string $currentVersion, array $payload): void
    {
        $runtime = is_array($payload['_runtime'] ?? null) ? $payload['_runtime'] : [];
        $expected = (string) ($runtime['version'] ?? $payload['expectedVersion'] ?? '');
        if ($expected === '' || $currentVersion === null || $currentVersion === '') {
            return;
        }

        if (!hash_equals($currentVersion, $expected)) {
            throw new RuntimeHttpException('STALE_RECORD', 'Este registro foi alterado por outro usuario. Recarregue antes de gravar.', 409, [
                'entityCode' => $entityCode,
                'actionId' => $actionId,
                'expectedVersion' => $expected,
                'currentVersion' => $currentVersion,
            ]);
        }
    }
}
