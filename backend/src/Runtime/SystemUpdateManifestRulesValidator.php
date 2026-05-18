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
