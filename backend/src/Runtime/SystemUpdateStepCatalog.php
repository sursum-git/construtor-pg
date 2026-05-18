<?php

namespace App\Runtime;

final class SystemUpdateStepCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'migrate' => [
                'code' => 'migrate',
                'title' => 'Aplicar migrations',
                'type' => 'database',
                'timeoutSeconds' => 900,
                'idempotent' => true,
                'rollbackStep' => null,
                'preconditions' => ['database_available'],
            ],
            'seed_runtime_metadata' => [
                'code' => 'seed_runtime_metadata',
                'title' => 'Atualizar metadados runtime',
                'type' => 'metadata',
                'timeoutSeconds' => 300,
                'idempotent' => true,
                'rollbackStep' => null,
                'preconditions' => ['database_available'],
            ],
            'publish_runtime_defaults' => [
                'code' => 'publish_runtime_defaults',
                'title' => 'Publicar catalogo padrao',
                'type' => 'runtime',
                'timeoutSeconds' => 300,
                'idempotent' => true,
                'rollbackStep' => null,
                'preconditions' => ['runtime_seeded'],
            ],
            'integrity_monitor' => [
                'code' => 'integrity_monitor',
                'title' => 'Verificar integridade estrutural',
                'type' => 'verification',
                'timeoutSeconds' => 180,
                'idempotent' => true,
                'rollbackStep' => null,
                'preconditions' => ['database_available'],
            ],
            'governance_monitor' => [
                'code' => 'governance_monitor',
                'title' => 'Verificar governanca operacional',
                'type' => 'verification',
                'timeoutSeconds' => 180,
                'idempotent' => true,
                'rollbackStep' => null,
                'preconditions' => ['runtime_seeded'],
            ],
            'dispatch_rollout' => [
                'code' => 'dispatch_rollout',
                'title' => 'Despachar rollout SaaS',
                'type' => 'orchestration',
                'timeoutSeconds' => 180,
                'idempotent' => false,
                'rollbackStep' => 'dispatch_rollback',
                'preconditions' => ['orchestrator_enabled'],
            ],
            'dispatch_rollback' => [
                'code' => 'dispatch_rollback',
                'title' => 'Despachar rollback SaaS',
                'type' => 'orchestration',
                'timeoutSeconds' => 180,
                'idempotent' => false,
                'rollbackStep' => null,
                'preconditions' => ['orchestrator_enabled'],
            ],
        ];
    }

    /**
     * @param array<int, mixed> $steps
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(array $steps): array
    {
        $normalized = [];
        foreach ($steps as $step) {
            $item = self::normalize($step);
            if ($item !== null) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $step
     * @return array<string, mixed>|null
     */
    public static function normalize(mixed $step): ?array
    {
        $catalog = self::definitions();
        if (is_string($step)) {
            $code = trim($step);
            if ($code === '') {
                return null;
            }

            return self::mergeWithCatalog([
                'code' => $code,
            ], $catalog[$code] ?? null);
        }

        if (!is_array($step)) {
            return null;
        }

        $code = trim((string) ($step['code'] ?? ''));
        if ($code === '') {
            return null;
        }

        return self::mergeWithCatalog($step, $catalog[$code] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed>|null $catalogEntry
     * @return array<string, mixed>
     */
    private static function mergeWithCatalog(array $step, ?array $catalogEntry): array
    {
        $code = trim((string) ($step['code'] ?? ''));
        $merged = array_merge($catalogEntry ?? [], $step);
        $merged['code'] = $code;
        $merged['title'] = trim((string) ($merged['title'] ?? '')) ?: ($catalogEntry['title'] ?? $code);
        $merged['type'] = trim((string) ($merged['type'] ?? '')) ?: ($catalogEntry['type'] ?? 'custom');
        $merged['timeoutSeconds'] = max(0, (int) ($merged['timeoutSeconds'] ?? ($catalogEntry['timeoutSeconds'] ?? 0)));
        $merged['idempotent'] = ($merged['idempotent'] ?? ($catalogEntry['idempotent'] ?? false)) === true;
        $merged['rollbackStep'] = trim((string) ($merged['rollbackStep'] ?? ($catalogEntry['rollbackStep'] ?? ''))) ?: null;
        $merged['preconditions'] = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, (array) ($merged['preconditions'] ?? ($catalogEntry['preconditions'] ?? []))), static fn (string $value): bool => $value !== ''));

        return $merged;
    }
}
