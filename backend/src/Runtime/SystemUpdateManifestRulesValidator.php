<?php

namespace App\Runtime;

class SystemUpdateManifestRulesValidator
{
    /**
     * @param array<int, array<string, mixed>> $releases
     */
    public function assertValid(array $releases): void
    {
        $issues = $this->collectIssues($releases);
        if ($issues === []) {
            return;
        }

        throw new \RuntimeException('Manifesto de atualizacoes invalido: ' . implode(' ', $issues));
    }

    /**
     * @param array<int, array<string, mixed>> $releases
     * @return list<string>
     */
    public function collectIssues(array $releases): array
    {
        $issues = [];
        $knownVersions = [];
        $knownStepCodes = array_flip(SystemUpdateStepCatalog::codes());

        foreach ($releases as $index => $release) {
            $version = trim((string) ($release['version'] ?? ''));
            if ($version === '') {
                $issues[] = 'Release na posicao ' . $index . ' sem version.';
                continue;
            }
            if (isset($knownVersions[$version])) {
                $issues[] = 'Version duplicada no manifesto: ' . $version . '.';
                continue;
            }
            $knownVersions[$version] = true;

            $requiresVersionMin = trim((string) ($release['requiresVersionMin'] ?? ''));
            if ($requiresVersionMin !== '' && version_compare($requiresVersionMin, $version, '>=')) {
                $issues[] = 'Release ' . $version . ' possui requiresVersionMin invalido: ' . $requiresVersionMin . '.';
            }
            $issues = array_merge($issues, $this->validateChannels($release, $version));
            $issues = array_merge($issues, $this->validateChangelog($release, $version));
            $issues = array_merge($issues, $this->validateSteps($release, $version, $knownStepCodes));
            $issues = array_merge($issues, $this->validateProgramUpdates($release, $version));
            $issues = array_merge($issues, $this->validateApplicationPolicy($release, $version));
        }

        foreach ($releases as $release) {
            $version = trim((string) ($release['version'] ?? ''));
            if ($version === '') {
                continue;
            }

            foreach ((array) ($release['requiresAppliedUpdates'] ?? []) as $requiredVersion) {
                $requiredVersion = trim((string) $requiredVersion);
                if ($requiredVersion === '') {
                    continue;
                }
                if ($requiredVersion === $version) {
                    $issues[] = 'Release ' . $version . ' nao pode depender dela mesma.';
                    continue;
                }
                if (!isset($knownVersions[$requiredVersion])) {
                    $issues[] = 'Release ' . $version . ' depende de version inexistente: ' . $requiredVersion . '.';
                    continue;
                }
                if (version_compare($requiredVersion, $version, '>=')) {
                    $issues[] = 'Release ' . $version . ' depende de version nao anterior: ' . $requiredVersion . '.';
                }
            }

            foreach ((array) ($release['replaces'] ?? []) as $replacedVersion) {
                $replacedVersion = trim((string) $replacedVersion);
                if ($replacedVersion === '') {
                    continue;
                }
                if ($replacedVersion === $version) {
                    $issues[] = 'Release ' . $version . ' nao pode substituir ela mesma.';
                    continue;
                }
                if (!isset($knownVersions[$replacedVersion])) {
                    $issues[] = 'Release ' . $version . ' referencia replaces inexistente: ' . $replacedVersion . '.';
                    continue;
                }
                if (version_compare($replacedVersion, $version, '>=')) {
                    $issues[] = 'Release ' . $version . ' so pode substituir versoes anteriores: ' . $replacedVersion . '.';
                }
            }
        }

        $cycles = $this->detectDependencyCycles($releases);
        foreach ($cycles as $cycle) {
            $issues[] = 'Ciclo de dependencia detectado: ' . implode(' -> ', $cycle) . '.';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param array<string, mixed> $release
     * @return list<string>
     */
    private function validateApplicationPolicy(array $release, string $version): array
    {
        $issues = [];
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $category = strtolower(trim((string) ($release['category'] ?? 'recommended')));

        $defaults = $this->resolveDefaultApplicationPolicy($category);
        $effective = [
            'requiresBackup' => array_key_exists('requiresBackup', $metadata) ? $metadata['requiresBackup'] === true : $defaults['requiresBackup'],
            'requiresMaintenanceMode' => array_key_exists('requiresMaintenanceMode', $metadata) ? $metadata['requiresMaintenanceMode'] === true : $defaults['requiresMaintenanceMode'],
            'autoApplySaas' => array_key_exists('autoApplySaas', $release) ? ($release['autoApplySaas'] ?? false) === true : $defaults['autoApplySaas'],
            'autoApplyOnPrem' => array_key_exists('autoApplyOnPrem', $release) ? ($release['autoApplyOnPrem'] ?? false) === true : $defaults['autoApplyOnPrem'],
            'requiresSubscriberConsent' => array_key_exists('requiresSubscriberConsent', $release) ? (($release['requiresSubscriberConsent'] ?? true) !== false) : $defaults['requiresSubscriberConsent'],
            'blocksNextUpdates' => array_key_exists('blocksNextUpdates', $release) ? (($release['blocksNextUpdates'] ?? false) === true) : $defaults['blocksNextUpdates'],
            'internetRequired' => array_key_exists('internetRequired', $release) ? (($release['internetRequired'] ?? false) === true) : $defaults['internetRequired'],
        ];
        $override = ($metadata['applicationPolicyOverride'] ?? false) === true;
        $overrideJustification = trim((string) ($metadata['applicationPolicyOverrideJustification'] ?? ''));

        if ($override && $overrideJustification === '') {
            $issues[] = 'Release ' . $version . ' marcou applicationPolicyOverride sem justificativa.';
        }

        $differsFromDefault = false;
        foreach ($effective as $field => $value) {
            if (($defaults[$field] ?? null) !== $value) {
                $differsFromDefault = true;
                break;
            }
        }
        if ($differsFromDefault && !$override) {
            $issues[] = 'Release ' . $version . ' diverge da politica padrao da categoria sem applicationPolicyOverride com justificativa.';
        }

        return $issues;
    }

    /**
     * @return array{requiresBackup: bool, requiresMaintenanceMode: bool, autoApplySaas: bool, autoApplyOnPrem: bool, requiresSubscriberConsent: bool, blocksNextUpdates: bool, internetRequired: bool}
     */
    private function resolveDefaultApplicationPolicy(string $category): array
    {
        return match ($category) {
            'security_critical' => [
                'requiresBackup' => false,
                'requiresMaintenanceMode' => false,
                'autoApplySaas' => true,
                'autoApplyOnPrem' => true,
                'requiresSubscriberConsent' => false,
                'blocksNextUpdates' => true,
                'internetRequired' => false,
            ],
            'required_structural' => [
                'requiresBackup' => true,
                'requiresMaintenanceMode' => true,
                'autoApplySaas' => true,
                'autoApplyOnPrem' => false,
                'requiresSubscriberConsent' => true,
                'blocksNextUpdates' => true,
                'internetRequired' => false,
            ],
            'optional_visual' => [
                'requiresBackup' => false,
                'requiresMaintenanceMode' => false,
                'autoApplySaas' => false,
                'autoApplyOnPrem' => false,
                'requiresSubscriberConsent' => true,
                'blocksNextUpdates' => false,
                'internetRequired' => false,
            ],
            default => [
                'requiresBackup' => false,
                'requiresMaintenanceMode' => false,
                'autoApplySaas' => false,
                'autoApplyOnPrem' => false,
                'requiresSubscriberConsent' => true,
                'blocksNextUpdates' => false,
                'internetRequired' => false,
            ],
        };
    }

    /**
     * @param array<string, mixed> $release
     * @return list<string>
     */
    private function validateProgramUpdates(array $release, string $version): array
    {
        $updates = (array) ($release['programUpdates'] ?? []);
        if ($updates === []) {
            return [];
        }

        $issues = [];
        $hasPublishDefaults = false;
        foreach (SystemUpdateStepCatalog::normalizeList((array) ($release['steps'] ?? [])) as $step) {
            if ((string) ($step['code'] ?? '') === 'publish_runtime_defaults') {
                $hasPublishDefaults = true;
                break;
            }
        }

        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        if (!$hasPublishDefaults && (($metadata['programUpdateHandledExternally'] ?? false) !== true)) {
            $issues[] = 'Release ' . $version . ' possui programUpdates sem publish_runtime_defaults ou sinalizacao externa explicita.';
        }

        foreach ($updates as $index => $update) {
            if (!is_array($update)) {
                $issues[] = 'Release ' . $version . ' possui programUpdate invalido na posicao ' . $index . '.';
                continue;
            }

            $programCode = trim((string) ($update['programCode'] ?? ''));
            if ($programCode === '') {
                $issues[] = 'Release ' . $version . ' possui programUpdate sem programCode na posicao ' . $index . '.';
            }

            $targetPublishedVersion = trim((string) ($update['targetPublishedVersion'] ?? ''));
            if ($targetPublishedVersion === '') {
                $issues[] = 'Release ' . $version . ' possui programUpdate sem targetPublishedVersion para ' . ($programCode !== '' ? $programCode : 'item ' . $index) . '.';
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $release
     * @return list<string>
     */
    private function validateChannels(array $release, string $version): array
    {
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $channels = $metadata['channels'] ?? $release['channels'] ?? [];
        $normalized = array_values(array_filter(array_map(static function ($value): string {
            return strtolower(trim((string) $value));
        }, (array) $channels), static fn (string $value): bool => $value !== ''));
        if ($normalized === []) {
            return [];
        }

        $allowed = ['stable', 'pilot', 'canary', 'lts'];
        $issues = [];
        foreach ($normalized as $channel) {
            if (!in_array($channel, $allowed, true)) {
                $issues[] = 'Release ' . $version . ' possui canal invalido: ' . $channel . '.';
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $release
     * @return list<string>
     */
    private function validateChangelog(array $release, string $version): array
    {
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $changelog = $metadata['changelog'] ?? [];
        if (!is_array($changelog)) {
            return ['Release ' . $version . ' possui changelog invalido.'];
        }

        $issues = [];
        foreach ($changelog as $index => $section) {
            if (!is_array($section)) {
                $issues[] = 'Release ' . $version . ' possui secao de changelog invalida na posicao ' . $index . '.';
                continue;
            }
            $title = trim((string) ($section['title'] ?? ''));
            if ($title === '') {
                $issues[] = 'Release ' . $version . ' possui secao de changelog sem titulo na posicao ' . $index . '.';
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $release
     * @param array<string, bool> $knownStepCodes
     * @return list<string>
     */
    private function validateSteps(array $release, string $version, array $knownStepCodes): array
    {
        $issues = [];
        foreach (SystemUpdateStepCatalog::normalizeList((array) ($release['steps'] ?? [])) as $step) {
            $code = trim((string) ($step['code'] ?? ''));
            if ($code === '') {
                $issues[] = 'Release ' . $version . ' possui step sem codigo.';
                continue;
            }
            if (!isset($knownStepCodes[$code])) {
                $issues[] = 'Release ' . $version . ' referencia step nao suportado: ' . $code . '.';
            }
            $rollbackStep = trim((string) ($step['rollbackStep'] ?? ''));
            if ($rollbackStep !== '' && !isset($knownStepCodes[$rollbackStep])) {
                $issues[] = 'Release ' . $version . ' referencia rollbackStep nao suportado: ' . $rollbackStep . '.';
            }
            foreach ((array) ($step['preconditions'] ?? []) as $precondition) {
                if (trim((string) $precondition) === '') {
                    $issues[] = 'Release ' . $version . ' possui precondicao vazia no step ' . $code . '.';
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $releases
     * @return list<list<string>>
     */
    private function detectDependencyCycles(array $releases): array
    {
        $graph = [];
        foreach ($releases as $release) {
            $version = trim((string) ($release['version'] ?? ''));
            if ($version === '') {
                continue;
            }
            $graph[$version] = array_values(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, (array) ($release['requiresAppliedUpdates'] ?? [])), static fn (string $value): bool => $value !== ''));
        }

        $visited = [];
        $stack = [];
        $cycles = [];
        foreach (array_keys($graph) as $node) {
            $this->visitDependencyNode($node, $graph, $visited, $stack, $cycles);
        }

        return $cycles;
    }

    /**
     * @param array<string, list<string>> $graph
     * @param array<string, bool> $visited
     * @param list<string> $stack
     * @param list<list<string>> $cycles
     */
    private function visitDependencyNode(string $node, array $graph, array &$visited, array &$stack, array &$cycles): void
    {
        if (($visited[$node] ?? false) === true) {
            return;
        }

        if (in_array($node, $stack, true)) {
            $start = array_search($node, $stack, true);
            if ($start !== false) {
                $cycles[] = array_merge(array_slice($stack, (int) $start), [$node]);
            }
            return;
        }

        $stack[] = $node;
        foreach ($graph[$node] ?? [] as $dependency) {
            if (!array_key_exists($dependency, $graph)) {
                continue;
            }
            $this->visitDependencyNode($dependency, $graph, $visited, $stack, $cycles);
        }
        array_pop($stack);
        $visited[$node] = true;
    }
}
