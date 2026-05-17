<?php

namespace App\Runtime;

use App\Entity\BuilderProgramOverlayVersion;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProgramOverlayService
{
    private const BLOCKING_CONFLICT_KEYS = ['pageType', 'screenId', 'runtime', 'permissions', 'dataModel', 'api'];

    public function __construct(
        private readonly BuilderProgramOverlayRepository $overlays,
        private readonly BuilderProgramOverlayVersionRepository $overlayVersions,
        private readonly BuilderProgramVersionRepository $programVersions,
        private readonly EntityManagerInterface $entityManager,
        private readonly StructuralIntegrityService $integrity,
    ) {
    }

    public function previewRebase(int $overlayId): array
    {
        $overlay = $this->overlays->find($overlayId);
        if (!$overlay) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_NOT_FOUND', 'Overlay de programa nao encontrado.', 404, ['overlayId' => $overlayId]);
        }
        $this->integrity->assertOverlay($overlay);

        $currentVersion = $this->overlayVersions->findLatestByOverlayId($overlayId);
        if (!$currentVersion) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_NOT_FOUND', 'Nenhuma versao de overlay foi encontrada para o rebase.', 404, ['overlayId' => $overlayId]);
        }
        $this->integrity->assertOverlayVersion($currentVersion);

        return $this->buildRebasePreview($currentVersion);
    }

    public function previewRebaseVersion(int $overlayVersionId): array
    {
        $currentVersion = $this->overlayVersions->find($overlayVersionId);
        if (!$currentVersion) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_NOT_FOUND', 'Versao de overlay nao encontrada.', 404, ['overlayVersionId' => $overlayVersionId]);
        }
        $this->integrity->assertOverlay($currentVersion->getOverlay());
        $this->integrity->assertOverlayVersion($currentVersion);

        return $this->buildRebasePreview($currentVersion);
    }

    public function rebase(int $overlayVersionId, array $resolutions = []): array
    {
        $currentVersion = $this->overlayVersions->find($overlayVersionId);
        if (!$currentVersion) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_NOT_FOUND', 'Versao de overlay nao encontrada.', 404, ['overlayVersionId' => $overlayVersionId]);
        }
        $this->integrity->assertOverlay($currentVersion->getOverlay());
        $this->integrity->assertOverlayVersion($currentVersion);

        $preview = $this->buildRebasePreview($currentVersion, $resolutions);
        if (($preview['status'] ?? '') === 'blocked') {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_REBASE_BLOCKED', 'O overlay nao pode ser rebaseado automaticamente.', 422, $preview);
        }
        $policyViolations = $this->validateRequestedResolutionsAgainstPolicy((array) ($preview['sections'] ?? []), $resolutions);
        if ($policyViolations) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_REBASE_POLICY_BLOCKED', 'O plano atual viola a politica de rebase para conflitos leves.', 422, [
                'overlayVersionId' => $overlayVersionId,
                'violations' => $policyViolations,
                'preview' => $preview,
            ]);
        }
        if (($preview['requiresConfirmation'] ?? false) === true && ($resolutions['__confirmWarning__'] ?? false) !== true) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_REBASE_CONFIRMATION_REQUIRED', 'O rebase possui conflitos leves e exige confirmacao explicita.', 422, $preview);
        }

        $overlay = $currentVersion->getOverlay();
        $nextVersionNumber = ($this->overlayVersions->findLatestByOverlayId((int) $overlay->getId())?->getVersionNumber() ?? 0) + 1;
        $newVersion = (new BuilderProgramOverlayVersion())
            ->setOverlay($overlay)
            ->setVersionNumber($nextVersionNumber)
            ->setStatus('draft')
            ->setSnapshot((array) ($currentVersion->getSnapshot() ?: []))
            ->setResolvedDefinition((array) ($preview['rebasedDefinition'] ?? $currentVersion->getResolvedDefinition()))
            ->setChangeSummary('Rebase assistido a partir da base ' . ($preview['targetBaseVersion'] ?? ''));

        $overlay
            ->setBaseProgramVersionId((int) ($preview['targetBaseVersionId'] ?? $overlay->getBaseProgramVersionId()))
            ->setUpgradeFrozen(false)
            ->setFrozenReason(null)
            ->setStatus('draft');

        $this->entityManager->persist($overlay);
        $this->entityManager->persist($newVersion);
        $this->entityManager->flush();
        $this->integrity->signOverlay($overlay, ['source' => 'overlayRebase']);
        $this->integrity->signOverlayVersion($newVersion, ['source' => 'overlayRebase']);

        return [
            'status' => $preview['status'],
            'overlayId' => $overlay->getId(),
            'newOverlayVersionId' => $newVersion->getId(),
            'preview' => $preview,
        ];
    }

    public function publishVersion(int $overlayVersionId): array
    {
        $version = $this->overlayVersions->find($overlayVersionId);
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_NOT_FOUND', 'Versao de overlay nao encontrada.', 404, ['overlayVersionId' => $overlayVersionId]);
        }

        $overlay = $version->getOverlay();
        $this->integrity->assertOverlay($overlay);
        $this->integrity->assertOverlayVersion($version);

        foreach ($this->overlayVersions->findByOverlayIdOrdered((int) $overlay->getId()) as $candidate) {
            if (!$candidate->getId() || $candidate->getId() === $version->getId()) {
                continue;
            }
            if ($candidate->getStatus() === 'published') {
                $candidate->setStatus('archived');
                $this->entityManager->persist($candidate);
            }
        }

        $version
            ->setStatus('published')
            ->setPublishedAt(new \DateTimeImmutable());
        $overlay
            ->setStatus('published')
            ->setUpgradeFrozen(false)
            ->setFrozenReason(null);

        $this->entityManager->persist($overlay);
        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->integrity->signOverlay($overlay, ['source' => 'overlayPublish']);
        $this->integrity->signOverlayVersion($version, ['source' => 'overlayPublish']);

        return [
            'overlayId' => $overlay->getId(),
            'overlayVersionId' => $version->getId(),
            'status' => 'published',
            'publishedAt' => $version->getPublishedAt()?->format(DATE_ATOM),
        ];
    }

    public function ensureRebaseDraftForPublishedOverlay(int $overlayId, string $releaseVersion = ''): array
    {
        $overlay = $this->overlays->find($overlayId);
        if (!$overlay) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_NOT_FOUND', 'Overlay de programa nao encontrado.', 404, ['overlayId' => $overlayId]);
        }
        $this->integrity->assertOverlay($overlay);

        $publishedVersion = $this->overlayVersions->findPublishedByOverlayId($overlayId);
        if (!$publishedVersion) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_NOT_FOUND', 'Overlay sem versao publicada para gerar rascunho de rebase.', 404, ['overlayId' => $overlayId]);
        }
        $this->integrity->assertOverlayVersion($publishedVersion);

        $preview = $this->buildRebasePreview($publishedVersion);
        if (($preview['status'] ?? '') === 'blocked' || ($preview['requiresConfirmation'] ?? false) === true) {
            return [
                'status' => (string) ($preview['status'] ?? 'blocked'),
                'draftCreated' => false,
                'preview' => $preview,
                'overlayId' => $overlay->getId(),
                'overlayVersionId' => $publishedVersion->getId(),
            ];
        }

        $latestVersion = $this->overlayVersions->findLatestByOverlayId($overlayId);
        if (
            $latestVersion
            && $latestVersion->getId()
            && $latestVersion->getStatus() === 'draft'
            && (int) $overlay->getBaseProgramVersionId() === (int) ($preview['targetBaseVersionId'] ?? 0)
        ) {
            $snapshot = $latestVersion->getSnapshot();
            $pipeline = is_array($snapshot['systemUpdatePipeline'] ?? null) ? $snapshot['systemUpdatePipeline'] : [];
            if (($pipeline['releaseVersion'] ?? '') === $releaseVersion && $releaseVersion !== '') {
                return [
                    'status' => 'draft_exists',
                    'draftCreated' => false,
                    'preview' => $preview,
                    'overlayId' => $overlay->getId(),
                    'overlayVersionId' => $publishedVersion->getId(),
                    'draftOverlayVersionId' => $latestVersion->getId(),
                ];
            }
        }

        $result = $this->rebase((int) $publishedVersion->getId(), []);
        $draftVersionId = (int) ($result['newOverlayVersionId'] ?? 0);
        $draftVersion = $draftVersionId > 0 ? $this->overlayVersions->find($draftVersionId) : null;
        if ($draftVersion) {
            $snapshot = $draftVersion->getSnapshot();
            $snapshot['systemUpdatePipeline'] = [
                'releaseVersion' => $releaseVersion,
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'systemUpdate',
                'targetBaseVersion' => $preview['targetBaseVersion'] ?? null,
                'targetBaseVersionId' => $preview['targetBaseVersionId'] ?? null,
            ];
            $draftVersion->setSnapshot($snapshot);
            $this->entityManager->persist($draftVersion);
        }

        $metadata = $overlay->getMetadata();
        $metadata['lastSystemUpdateRebase'] = [
            'releaseVersion' => $releaseVersion,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'draftOverlayVersionId' => $draftVersion?->getId(),
            'targetBaseVersion' => $preview['targetBaseVersion'] ?? null,
            'targetBaseVersionId' => $preview['targetBaseVersionId'] ?? null,
        ];
        $overlay->setMetadata($metadata);
        $this->entityManager->persist($overlay);
        $this->entityManager->flush();
        $this->integrity->signOverlay($overlay, ['source' => 'overlayRebaseDraft']);
        if ($draftVersion) {
            $this->integrity->signOverlayVersion($draftVersion, ['source' => 'overlayRebaseDraft']);
        }

        return [
            'status' => 'draft_created',
            'draftCreated' => true,
            'preview' => $preview,
            'overlayId' => $overlay->getId(),
            'overlayVersionId' => $publishedVersion->getId(),
            'draftOverlayVersionId' => $draftVersion?->getId(),
        ];
    }

    public function compareVersions(int $leftVersionId, int $rightVersionId): array
    {
        $left = $this->overlayVersions->find($leftVersionId);
        $right = $this->overlayVersions->find($rightVersionId);
        if (!$left || !$right) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_COMPARE_NOT_FOUND', 'Uma das versoes de overlay informadas nao foi encontrada.', 404, [
                'leftVersionId' => $leftVersionId,
                'rightVersionId' => $rightVersionId,
            ]);
        }

        $this->integrity->assertOverlay($left->getOverlay());
        $this->integrity->assertOverlayVersion($left);
        $this->integrity->assertOverlay($right->getOverlay());
        $this->integrity->assertOverlayVersion($right);

        return $this->buildVersionComparison($left, $right);
    }

    private function buildRebasePreview(BuilderProgramOverlayVersion $currentVersion, array $resolutions = []): array
    {
        $overlay = $currentVersion->getOverlay();
        $basePublished = $this->programVersions->findPublishedByProgramCode($overlay->getProgramCode());
        if (!$basePublished) {
            throw new RuntimeHttpException('PROGRAM_BASE_VERSION_NOT_FOUND', 'Programa base publicado nao encontrado para o overlay.', 404, [
                'programCode' => $overlay->getProgramCode(),
            ]);
        }

        $oldBase = $overlay->getBaseProgramVersionId() ? $this->programVersions->find($overlay->getBaseProgramVersionId()) : null;
        $oldDefinition = is_array($oldBase?->getGeneratedDefinition()) ? $oldBase->getGeneratedDefinition() : [];
        $newDefinition = is_array($basePublished->getGeneratedDefinition()) ? $basePublished->getGeneratedDefinition() : [];
        $resolvedCurrent = is_array($currentVersion->getResolvedDefinition()) ? $currentVersion->getResolvedDefinition() : [];
        $snapshot = is_array($currentVersion->getSnapshot()) ? $currentVersion->getSnapshot() : [];
        $overrides = is_array($snapshot['definitionOverrides'] ?? null) ? $snapshot['definitionOverrides'] : [];

        $baseChangedKeys = $this->topLevelChangedKeys($oldDefinition, $newDefinition);
        $overlayChangedKeys = $this->topLevelChangedKeys($oldDefinition, $resolvedCurrent);
        $conflicts = array_values(array_intersect($baseChangedKeys, $overlayChangedKeys));
        $allChangedKeys = array_values(array_unique(array_merge($baseChangedKeys, $overlayChangedKeys)));
        sort($allChangedKeys);

        $sections = array_map(function (string $key) use ($baseChangedKeys, $overlayChangedKeys, $conflicts): array {
            $baseChanged = in_array($key, $baseChangedKeys, true);
            $overlayChanged = in_array($key, $overlayChangedKeys, true);
            $conflict = in_array($key, $conflicts, true);
            $classification = 'unchanged';
            if ($conflict) {
                $classification = in_array($key, self::BLOCKING_CONFLICT_KEYS, true) ? 'conflict_blocking' : 'conflict_warning';
            } elseif ($baseChanged && !$overlayChanged) {
                $classification = 'auto_merge';
            } elseif (!$baseChanged && $overlayChanged) {
                $classification = 'overlay_only';
            } elseif ($baseChanged && $overlayChanged) {
                $classification = 'auto_merge';
            }

            return [
                'key' => $key,
                'baseChanged' => $baseChanged,
                'overlayChanged' => $overlayChanged,
                'conflict' => $conflict,
                'classification' => $classification,
                'resolution' => $classification === 'conflict_blocking'
                    ? 'Revisao manual obrigatoria'
                    : ($classification === 'conflict_warning'
                        ? 'Revisar diferencas antes de publicar'
                        : ($classification === 'overlay_only'
                            ? 'Manter override do assinante'
                            : ($classification === 'auto_merge'
                                ? 'Aplicacao automatica da nova base'
                                : 'Sem diferencas relevantes'))),
                'basePaths' => [],
                'overlayPaths' => [],
                'conflictPaths' => [],
            ];
        }, $allChangedKeys);
        $blockingConflicts = array_values(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'conflict_blocking'));
        $warningConflicts = array_values(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'conflict_warning'));

        $status = 'ok';
        $reason = 'Rebase automatico disponivel.';
        if ($overlay->getCustomizationKind() === 'customer_custom' && $overlay->isUpgradeFrozen()) {
            $status = 'blocked';
            $reason = 'Variante `customer_custom` congelada para upgrade automatico.';
        } elseif (($oldDefinition['pageType'] ?? null) !== null && ($newDefinition['pageType'] ?? null) !== null && ($oldDefinition['pageType'] ?? null) !== ($newDefinition['pageType'] ?? null)) {
            $status = 'blocked';
            $reason = 'A base mudou o tipo de pagina e exige revisao manual.';
        } elseif ($blockingConflicts) {
            $status = 'blocked';
            $reason = 'Existem conflitos bloqueantes em secoes estruturais do contrato.';
        } elseif ($warningConflicts) {
            $status = 'warning';
            $reason = 'A base e o overlay alteraram as mesmas secoes do contrato.';
        }

        $rebasedDefinition = $newDefinition;
        if ($overrides) {
            $rebasedDefinition = array_replace_recursive($newDefinition, $overrides);
        } elseif ($status === 'ok') {
            $rebasedDefinition = $resolvedCurrent ?: $newDefinition;
        }
        if ($resolutions) {
            $rebasedDefinition = $this->applyResolutions($rebasedDefinition, $resolutions, $sections, $oldDefinition, $newDefinition, $resolvedCurrent);
        }
        foreach ($sections as $index => $section) {
            $key = (string) ($section['key'] ?? '');
            $sections[$index]['basePaths'] = $this->changedPaths($oldDefinition[$key] ?? null, $newDefinition[$key] ?? null, $key);
            $sections[$index]['overlayPaths'] = $this->changedPaths($oldDefinition[$key] ?? null, $resolvedCurrent[$key] ?? null, $key);
            if (($section['conflict'] ?? false) === true) {
                $sections[$index]['conflictPaths'] = array_values(array_intersect($sections[$index]['basePaths'], $sections[$index]['overlayPaths']));
            }
            $sections[$index]['entries'] = $this->buildSectionEntries(
                $key,
                $oldDefinition[$key] ?? null,
                $newDefinition[$key] ?? null,
                $resolvedCurrent[$key] ?? null,
                $rebasedDefinition[$key] ?? null,
                $sections[$index]['basePaths'],
                $sections[$index]['overlayPaths'],
                $sections[$index]['conflictPaths'],
                (string) ($sections[$index]['classification'] ?? 'unchanged')
            );
        }

        $resolutionSummary = $this->buildResolutionSummary($sections, $resolutions);
        $runtimeImpact = $this->buildRuntimeImpactSummary($sections);
        $policySummary = $this->buildPolicySummary($sections, $resolutions);
        $finalDiffEntries = $this->buildFinalDiffEntries($resolvedCurrent, $rebasedDefinition);

        return [
            'status' => $status,
            'reason' => $reason,
            'canApply' => $status !== 'blocked',
            'requiresConfirmation' => $status === 'warning',
            'policyDecision' => $status === 'blocked'
                ? 'Conflito bloqueante: rebase proibido ate revisao manual.'
                : ($status === 'warning'
                    ? 'Conflito leve: rebase permitido apenas com confirmacao explicita.'
                    : 'Sem bloqueios: rebase pode seguir normalmente.'),
            'overlayId' => $overlay->getId(),
            'overlayVersionId' => $currentVersion->getId(),
            'customizationKind' => $overlay->getCustomizationKind(),
            'currentBaseVersionId' => $overlay->getBaseProgramVersionId(),
            'currentBaseVersion' => $oldBase?->getVersion(),
            'targetBaseVersionId' => $basePublished->getId(),
            'targetBaseVersion' => $basePublished->getVersion(),
            'baseChangedKeys' => $baseChangedKeys,
            'overlayChangedKeys' => $overlayChangedKeys,
            'conflicts' => $conflicts,
            'oldBaseDefinition' => $oldDefinition,
            'targetBaseDefinition' => $newDefinition,
            'currentResolvedDefinition' => $resolvedCurrent,
            'definitionOverrides' => $overrides,
            'summaryCounts' => [
                'autoMerge' => count(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'auto_merge')),
                'overlayOnly' => count(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'overlay_only')),
                'warningConflicts' => count($warningConflicts),
                'blockingConflicts' => count($blockingConflicts),
            ],
            'runtimeImpactSummary' => $runtimeImpact,
            'finalResolutionSummary' => $resolutionSummary,
            'policySummary' => $policySummary,
            'finalDiffEntries' => $finalDiffEntries,
            'finalDiffDefinition' => $this->buildFinalDiffDefinition($finalDiffEntries),
            'requestedResolutions' => $resolutions,
            'sections' => $sections,
            'rebasedDefinition' => $rebasedDefinition,
        ];
    }

    private function buildVersionComparison(BuilderProgramOverlayVersion $left, BuilderProgramOverlayVersion $right): array
    {
        $leftDefinition = is_array($left->getResolvedDefinition()) ? $left->getResolvedDefinition() : [];
        $rightDefinition = is_array($right->getResolvedDefinition()) ? $right->getResolvedDefinition() : [];
        $changedKeys = $this->topLevelChangedKeys($leftDefinition, $rightDefinition);
        $sections = [];
        foreach ($changedKeys as $key) {
            $leftPaths = $this->changedPaths($leftDefinition[$key] ?? null, $rightDefinition[$key] ?? null, $key);
            $entries = $this->buildSectionEntries(
                $key,
                $leftDefinition[$key] ?? null,
                $rightDefinition[$key] ?? null,
                $rightDefinition[$key] ?? null,
                $rightDefinition[$key] ?? null,
                $leftPaths,
                $leftPaths,
                [],
                'auto_merge'
            );
            $sections[] = [
                'key' => $key,
                'classification' => 'changed',
                'pathCount' => count($leftPaths),
                'paths' => $leftPaths,
                'entries' => $entries,
            ];
        }

        return [
            'leftVersion' => [
                'id' => $left->getId(),
                'versionNumber' => $left->getVersionNumber(),
                'status' => $left->getStatus(),
                'changeSummary' => $left->getChangeSummary(),
                'publishedAt' => $left->getPublishedAt()?->format(DATE_ATOM),
            ],
            'rightVersion' => [
                'id' => $right->getId(),
                'versionNumber' => $right->getVersionNumber(),
                'status' => $right->getStatus(),
                'changeSummary' => $right->getChangeSummary(),
                'publishedAt' => $right->getPublishedAt()?->format(DATE_ATOM),
            ],
            'changedSections' => count($sections),
            'changedPaths' => array_sum(array_map(static fn (array $item): int => (int) ($item['pathCount'] ?? 0), $sections)),
            'sections' => $sections,
        ];
    }

    private function topLevelChangedKeys(array $before, array $after): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changed = [];
        foreach ($keys as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changed[] = (string) $key;
            }
        }

        sort($changed);
        return $changed;
    }

    /**
     * @return list<string>
     */
    private function changedPaths(mixed $before, mixed $after, string $prefix): array
    {
        if ($before === $after) {
            return [];
        }
        if (!$this->isAssocArray($before) && !$this->isAssocArray($after)) {
            if (is_array($before) || is_array($after)) {
                return [$prefix];
            }

            return [$prefix];
        }

        $beforeArray = is_array($before) ? $before : [];
        $afterArray = is_array($after) ? $after : [];
        $keys = array_values(array_unique(array_merge(array_keys($beforeArray), array_keys($afterArray))));
        $paths = [];
        foreach ($keys as $key) {
            $childPrefix = $prefix . '.' . (string) $key;
            $paths = array_merge($paths, $this->changedPaths($beforeArray[$key] ?? null, $afterArray[$key] ?? null, $childPrefix));
        }

        if (!$paths) {
            $paths[] = $prefix;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param list<string> $basePaths
     * @param list<string> $overlayPaths
     * @param list<string> $conflictPaths
     * @return list<array<string, mixed>>
     */
    private function buildSectionEntries(
        string $sectionKey,
        mixed $oldSection,
        mixed $newSection,
        mixed $overlaySection,
        mixed $rebasedSection,
        array $basePaths,
        array $overlayPaths,
        array $conflictPaths,
        string $sectionClassification,
    ): array {
        $allPaths = array_values(array_unique(array_merge($basePaths, $overlayPaths)));
        sort($allPaths);
        $entries = [];
        foreach ($allPaths as $path) {
            $baseChanged = in_array($path, $basePaths, true);
            $overlayChanged = in_array($path, $overlayPaths, true);
            $conflict = in_array($path, $conflictPaths, true);
            $classification = 'unchanged';
            if ($conflict) {
                $classification = $sectionClassification === 'conflict_blocking' ? 'conflict_blocking' : 'conflict_warning';
            } elseif ($baseChanged && !$overlayChanged) {
                $classification = 'auto_merge';
            } elseif (!$baseChanged && $overlayChanged) {
                $classification = 'overlay_only';
            } elseif ($baseChanged && $overlayChanged) {
                $classification = 'auto_merge';
            }
            $relativePath = $path === $sectionKey
                ? ''
                : (str_starts_with($path, $sectionKey . '.') ? substr($path, strlen($sectionKey) + 1) : $path);
            $tokens = $this->pathTokens($relativePath);
            $entries[] = [
                'path' => $path,
                'relativePath' => $relativePath,
                'baseChanged' => $baseChanged,
                'overlayChanged' => $overlayChanged,
                'conflict' => $conflict,
                'classification' => $classification,
                'selectedResolution' => $this->defaultResolutionForClassification($classification),
                'resolutionOptions' => $this->resolutionOptionsForClassification($classification),
                'oldValue' => $this->resolvePathValue($oldSection, $tokens),
                'baseValue' => $this->resolvePathValue($newSection, $tokens),
                'overlayValue' => $this->resolvePathValue($overlaySection, $tokens),
                'rebasedValue' => $this->resolvePathValue($rebasedSection, $tokens),
            ];
        }

        return $entries;
    }

    /**
     * @return list<string|int>
     */
    private function pathTokens(string $path): array
    {
        if ($path === '') {
            return [];
        }
        return array_map(static function (string $token): string|int {
            return ctype_digit($token) ? (int) $token : $token;
        }, explode('.', $path));
    }

    private function resolvePathValue(mixed $value, array $tokens): mixed
    {
        $current = $value;
        foreach ($tokens as $token) {
            if (!is_array($current) || !array_key_exists($token, $current)) {
                return null;
            }
            $current = $current[$token];
        }

        return $current;
    }

    private function isAssocArray(mixed $value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function defaultResolutionForClassification(string $classification): string
    {
        return match ($classification) {
            'overlay_only' => 'overlay',
            'conflict_warning', 'conflict_blocking', 'auto_merge' => 'rebased',
            default => 'rebased',
        };
    }

    /**
     * @return list<string>
     */
    private function resolutionOptionsForClassification(string $classification): array
    {
        return match ($classification) {
            'conflict_warning', 'conflict_blocking' => ['rebased', 'base', 'overlay'],
            'overlay_only' => ['overlay', 'base', 'rebased'],
            'auto_merge' => ['rebased', 'base', 'overlay'],
            default => ['rebased'],
        };
    }

    private function applyResolutions(
        array $rebasedDefinition,
        array $requestedResolutions,
        array $sections,
        array $oldDefinition,
        array $newDefinition,
        array $resolvedCurrent,
    ): array {
        $allowed = [];
        foreach ($sections as $section) {
            foreach ((array) ($section['entries'] ?? []) as $entry) {
                $path = (string) ($entry['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $allowed[$path] = [
                    'classification' => (string) ($entry['classification'] ?? 'unchanged'),
                    'baseValue' => $entry['baseValue'] ?? null,
                    'overlayValue' => $entry['overlayValue'] ?? null,
                    'rebasedValue' => $entry['rebasedValue'] ?? null,
                    'oldValue' => $entry['oldValue'] ?? null,
                ];
            }
        }

        foreach ($requestedResolutions as $path => $resolution) {
            $normalizedPath = trim((string) $path);
            $normalizedResolution = strtolower(trim((string) $resolution));
            if ($normalizedPath === '' || !isset($allowed[$normalizedPath])) {
                continue;
            }
            if (!in_array($normalizedResolution, ['base', 'overlay', 'rebased'], true)) {
                continue;
            }
            $resolvedValue = match ($normalizedResolution) {
                'base' => $allowed[$normalizedPath]['baseValue'],
                'overlay' => $allowed[$normalizedPath]['overlayValue'],
                default => $allowed[$normalizedPath]['rebasedValue'],
            };
            $this->assignPathValue($rebasedDefinition, $normalizedPath, $resolvedValue);
        }

        return $rebasedDefinition;
    }

    private function buildResolutionSummary(array $sections, array $requestedResolutions): array
    {
        $summary = ['rebased' => 0, 'overlay' => 0, 'base' => 0];
        foreach ($sections as $section) {
            foreach ((array) ($section['entries'] ?? []) as $entry) {
                $path = (string) ($entry['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $selected = strtolower(trim((string) ($requestedResolutions[$path] ?? $entry['selectedResolution'] ?? $entry['defaultResolution'] ?? 'rebased')));
                if (!array_key_exists($selected, $summary)) {
                    $selected = 'rebased';
                }
                $summary[$selected]++;
            }
        }

        return $summary;
    }

    private function buildPolicySummary(array $sections, array $requestedResolutions): array
    {
        $violations = $this->validateRequestedResolutionsAgainstPolicy($sections, $requestedResolutions);
        $criticalWarnings = 0;
        foreach ($sections as $section) {
            foreach ((array) ($section['entries'] ?? []) as $entry) {
                if (($entry['classification'] ?? '') === 'conflict_warning') {
                    $criticalWarnings++;
                }
            }
        }

        return [
            'criticalWarningPaths' => $criticalWarnings,
            'violationCount' => count($violations),
            'violations' => $violations,
            'message' => $violations
                ? 'Existem escolhas no plano atual que violam a politica de rebase para conflitos leves.'
                : ($criticalWarnings > 0
                    ? 'Conflitos leves aceitam apenas rebase sugerido ou base publicada.'
                    : 'Sem restricoes adicionais de politica no plano atual.'),
        ];
    }

    private function buildRuntimeImpactSummary(array $sections): array
    {
        return [
            'criticalSections' => count(array_filter($sections, static fn (array $item): bool => in_array((string) ($item['key'] ?? ''), self::BLOCKING_CONFLICT_KEYS, true))),
            'blockingConflicts' => count(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'conflict_blocking')),
            'warningConflicts' => count(array_filter($sections, static fn (array $item): bool => ($item['classification'] ?? '') === 'conflict_warning')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFinalDiffEntries(array $currentDefinition, array $finalDefinition): array
    {
        $paths = $this->changedPaths($currentDefinition, $finalDefinition, 'root');
        $items = [];
        foreach ($paths as $path) {
            $relativePath = $path === 'root'
                ? ''
                : (str_starts_with($path, 'root.') ? substr($path, 5) : $path);
            if ($relativePath === '') {
                continue;
            }
            $tokens = $this->pathTokens($relativePath);
            $items[] = [
                'path' => $relativePath,
                'currentValue' => $this->resolvePathValue($currentDefinition, $tokens),
                'finalValue' => $this->resolvePathValue($finalDefinition, $tokens),
                'selectedResolution' => 'final',
                'classification' => 'changed',
            ];
        }

        return $items;
    }

    private function buildFinalDiffDefinition(array $entries): array
    {
        $definition = [];
        foreach ($entries as $entry) {
            $path = trim((string) ($entry['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $this->assignPathValue($definition, $path, $entry['finalValue'] ?? null);
        }

        return $definition;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validateRequestedResolutionsAgainstPolicy(array $sections, array $requestedResolutions): array
    {
        $violations = [];
        foreach ($sections as $section) {
            foreach ((array) ($section['entries'] ?? []) as $entry) {
                if (($entry['classification'] ?? '') !== 'conflict_warning') {
                    continue;
                }
                $path = (string) ($entry['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $selected = strtolower(trim((string) ($requestedResolutions[$path] ?? $entry['selectedResolution'] ?? 'rebased')));
                if ($selected === 'overlay') {
                    $violations[] = [
                        'path' => $path,
                        'section' => (string) ($section['key'] ?? ''),
                        'selectedResolution' => $selected,
                        'allowedResolutions' => ['rebased', 'base'],
                    ];
                }
            }
        }

        return $violations;
    }

    private function assignPathValue(array &$definition, string $path, mixed $value): void
    {
        $tokens = $this->pathTokens($path);
        if (!$tokens) {
            return;
        }
        $current = &$definition;
        foreach ($tokens as $index => $token) {
            $isLast = $index === count($tokens) - 1;
            if ($isLast) {
                if (is_array($current)) {
                    $current[$token] = $value;
                }
                return;
            }
            if (!is_array($current)) {
                return;
            }
            if (!array_key_exists($token, $current) || !is_array($current[$token])) {
                $current[$token] = [];
            }
            $current = &$current[$token];
        }
    }
}
