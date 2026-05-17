<?php

namespace App\Runtime;

use App\Entity\BuilderProgramOverlay;
use App\Entity\AuthSubscriber;
use App\Entity\SystemUpdateConsent;
use App\Entity\SystemUpdateExecution;
use App\Entity\SystemUpdateRelease;
use App\Repository\AuthSubscriberRepository;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Repository\SystemUpdateConsentRepository;
use App\Repository\SystemUpdateExecutionRepository;
use App\Repository\SystemUpdateReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;

class SystemUpdateService
{
    public function __construct(
        private readonly SystemUpdateManifestLoader $manifestLoader,
        private readonly SystemUpdateReleaseRepository $releases,
        private readonly SystemUpdateConsentRepository $consents,
        private readonly SystemUpdateExecutionRepository $executions,
        private readonly RuntimeAsyncJobService $jobs,
        private readonly RuntimeAsyncJobRepository $runtimeJobs,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
        private readonly DeploymentModeResolver $deploymentMode,
        private readonly SystemVersionResolver $systemVersion,
        private readonly PermissionResolver $permissions,
        private readonly ProgramOverlayService $overlayService,
        private readonly BuilderProgramOverlayRepository $overlays,
        private readonly BuilderProgramOverlayVersionRepository $overlayVersions,
        private readonly BuilderProgramVersionRepository $programVersions,
        private readonly AuthSubscriberRepository $subscribers,
        private readonly CentralControlResolver $central,
        private readonly SystemUpdateStepRunner $steps,
        private readonly SystemUpdatePackageDownloader $packages,
        private readonly SystemUpdateOrchestratorClient $orchestrator,
    ) {
    }

    public function bootstrap(bool $autoQueue = false, ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $check = $this->check(null, true, $autoQueue, $targetSubscriber?->getCode());

        return [
            'centralControl' => $this->central->resolve(),
            'summary' => $check['summary'],
            'releases' => $check['releases'],
            'subscribers' => $this->listTargetSubscribers(),
            'selectedSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'consents' => $this->listConsents($targetSubscriber?->getCode()),
            'executions' => $this->listExecutions($targetSubscriber?->getCode()),
            'jobs' => $this->listJobs(),
        ];
    }

    public function check(?string $source = null, bool $persist = true, bool $autoQueue = false, ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $manifest = $this->manifestLoader->load($source);
        $normalized = array_map(fn (array $item): array => $this->normalizeRelease($item, $manifest['source'], $manifest['hash']), $manifest['releases']);
        usort($normalized, fn (array $left, array $right): int => version_compare((string) $left['version'], (string) $right['version']));

        if ($persist) {
            foreach ($normalized as $releaseData) {
                $release = $this->releases->findOneByVersion($releaseData['version']) ?? new SystemUpdateRelease();
                $this->hydrateRelease($release, $releaseData);
                $this->entityManager->persist($release);
            }
            $this->entityManager->flush();
        }

        $deploymentMode = $this->deploymentMode->resolve();
        $environment = $this->environmentIdentity->resolve();
        $currentVersion = $this->resolveCurrentVersion();
        $appliedVersions = $this->resolveAppliedVersions();

        $items = [];
        $manifestTrusted = $this->isManifestTrusted((string) ($manifest['signatureStatus'] ?? 'unknown'));
        foreach ($normalized as $releaseData) {
            $items[] = $this->evaluateRelease($releaseData, $currentVersion, $appliedVersions, $deploymentMode, $manifestTrusted, (string) ($manifest['signatureMessage'] ?? ''), $targetSubscriber?->getCode());
        }

        $autoQueued = null;
        if ($autoQueue && $manifestTrusted) {
            $autoQueued = $this->queueAutomaticRelease($items, $deploymentMode);
        }

        return [
            'summary' => [
                'currentVersion' => $currentVersion,
                'deploymentMode' => $deploymentMode,
                'databaseEnvironment' => $environment['databaseEnvironment'] ?? 'dev',
                'databaseIdentity' => $environment['databaseIdentity'] ?? 'db:dev',
                'targetSubscriberCode' => $targetSubscriber?->getCode(),
                'targetSubscriberName' => $targetSubscriber?->getName(),
                'manifestSource' => $manifest['source'],
                'manifestHash' => $manifest['hash'],
                'manifestMeta' => $manifest['meta'] ?? [],
                'manifestSignatureStatus' => $manifest['signatureStatus'] ?? 'unknown',
                'manifestSignatureMessage' => $manifest['signatureMessage'] ?? '',
                'pendingCount' => count(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'pending')),
                'blockingCount' => count(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'blocked_dependency')),
                'criticalPendingCount' => count(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['severity'] ?? '') === 'critical')),
                'autoQueuedRelease' => $autoQueued,
            ],
            'releases' => $items,
        ];
    }

    public function queueApply(string $version, bool $forceConsent = false, string $mode = 'manual', string $source = 'ui', ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $release = $this->requireRelease($version);
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        if (($evaluation['status'] ?? '') !== 'pending') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_NOT_PENDING', 'A release nao esta pendente para aplicacao.', 422, $evaluation);
        }
        if (($evaluation['requiresConsent'] ?? false) === true && ($evaluation['consentApproved'] ?? false) !== true && $forceConsent !== true) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_CONSENT_REQUIRED', 'A release exige anuencia do assinante antes da aplicacao.', 422, $evaluation);
        }
        if ($this->executions->hasExecutionInStatuses($release->getVersion(), ['queued', 'running'])) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_ALREADY_RUNNING', 'Ja existe execucao de atualizacao em andamento para esta release.', 409, [
                'version' => $release->getVersion(),
            ]);
        }

        $execution = $this->newExecution($release, $mode, $source, $evaluation['impactReport'] ?? [], $targetSubscriber);
        $execution->setStatus('queued');
        $execution->setSummary([
            'message' => 'Atualizacao enfileirada.',
            'releaseVersion' => $release->getVersion(),
        ]);
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        $this->jobs->schedule('system.update.apply', [
            'releaseVersion' => $release->getVersion(),
            'executionId' => $execution->getId(),
            'forceConsent' => $forceConsent,
            'mode' => $mode,
            'source' => $source,
            'targetSubscriberCode' => $targetSubscriber?->getCode(),
        ], [
            'screenId' => 'admin.atualizacoes',
            'programId' => 'admin-atualizacoes',
            'actionId' => 'applySystemUpdate',
            'entityCode' => 'system_update_release',
            'recordId' => $release->getVersion(),
            'message' => 'Atualizacao do sistema enfileirada.',
        ]);
        $queued = $this->jobs->flushPending();
        $jobId = (int) (($queued[0]['id'] ?? 0));
        $execution->setRuntimeJobId($jobId > 0 ? $jobId : null);
        $this->entityManager->flush();

        return [
            'execution' => $this->formatExecution($execution),
            'job' => $jobId > 0 ? $this->getJob($jobId) : null,
        ];
    }

    public function downloadPackage(string $version, ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $release = $this->requireRelease($version);
        $package = $this->packages->download($this->resolveReleasePayload($release));

        return [
            'releaseVersion' => $release->getVersion(),
            'targetSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'package' => $package,
        ];
    }

    public function dispatchRollout(string $version, ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $release = $this->requireRelease($version);
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        $package = null;
        $resolvedRelease = $this->resolveReleasePayload($release);
        if (trim((string) (($resolvedRelease['metadata']['packageUrl'] ?? ''))) !== '') {
            $package = $this->packages->download($resolvedRelease);
        }

        $payload = $this->buildOrchestratorPayload($release, $evaluation, $targetSubscriber, $package);
        $dispatch = $this->orchestrator->dispatch($payload);

        return [
            'releaseVersion' => $release->getVersion(),
            'targetSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'dispatch' => $dispatch,
            'payload' => $payload,
        ];
    }

    public function applyRelease(string $version, ?int $executionId = null, bool $forceConsent = false, string $mode = 'manual', string $source = 'job', ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $release = $this->requireRelease($version);
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        if (($evaluation['status'] ?? '') !== 'pending') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_NOT_PENDING', 'A release nao esta pendente para aplicacao.', 422, $evaluation);
        }
        if (($evaluation['requiresConsent'] ?? false) === true && ($evaluation['consentApproved'] ?? false) !== true && $forceConsent !== true) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_CONSENT_REQUIRED', 'A release exige anuencia do assinante antes da aplicacao.', 422, $evaluation);
        }

        $execution = $executionId ? $this->executions->find($executionId) : null;
        if (!$execution) {
            $execution = $this->newExecution($release, $mode, $source, $evaluation['impactReport'] ?? [], $targetSubscriber);
            $this->entityManager->persist($execution);
        }

        $execution
            ->setStatus('running')
            ->setSummary([
                'message' => 'Atualizacao em execucao.',
                'releaseVersion' => $release->getVersion(),
            ])
            ->setImpactReport((array) ($evaluation['impactReport'] ?? []));
        $this->entityManager->flush();

        $resolvedRelease = $this->resolveReleasePayload($release);
        $metadata = is_array($resolvedRelease['metadata'] ?? null) ? $resolvedRelease['metadata'] : [];
        $packageInfo = null;
        if (trim((string) ($metadata['packageUrl'] ?? '')) !== '') {
            try {
                $packageInfo = $this->packages->download($resolvedRelease);
                $execution->setSummary([
                    'message' => 'Atualizacao em execucao.',
                    'releaseVersion' => $release->getVersion(),
                    'package' => $packageInfo,
                ]);
                $this->entityManager->flush();
            } catch (\Throwable $error) {
                $execution
                    ->setStatus('failed')
                    ->setErrorMessage($error->getMessage())
                    ->setSummary([
                        'message' => 'Falha ao validar o pacote da atualizacao.',
                        'releaseVersion' => $release->getVersion(),
                    ])
                    ->setFinishedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return [
                    'status' => 'failed',
                    'releaseVersion' => $release->getVersion(),
                    'package' => null,
                    'steps' => [],
                    'impactReport' => $execution->getImpactReport(),
                ];
            }
        }

        $stepResults = [];
        foreach ($release->getSteps() as $step) {
            $result = $this->steps->run((string) $step);
            $stepResults[] = $result;
            if (($result['status'] ?? '') !== 'ok') {
                $execution
                    ->setStatus('failed')
                    ->setErrorMessage('Falha ao executar o passo ' . $step . '.')
                    ->setSummary([
                        'message' => 'Falha na atualizacao.',
                        'failedStep' => $step,
                        'steps' => $stepResults,
                    ])
                    ->setFinishedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return [
                    'status' => 'failed',
                    'releaseVersion' => $release->getVersion(),
                    'package' => $packageInfo,
                    'steps' => $stepResults,
                    'impactReport' => $execution->getImpactReport(),
                ];
            }
        }

        $orchestratorDispatch = null;
        if ($this->shouldDispatchRollout($release)) {
            try {
                $orchestratorDispatch = $this->orchestrator->dispatch(
                    $this->buildOrchestratorPayload($release, $evaluation, $targetSubscriber, $packageInfo, $execution)
                );
            } catch (\Throwable $error) {
                $execution
                    ->setStatus('failed')
                    ->setErrorMessage($error->getMessage())
                    ->setSummary([
                        'message' => 'Falha ao despachar rollout SaaS para o orquestrador.',
                        'releaseVersion' => $release->getVersion(),
                        'steps' => $stepResults,
                        'package' => $packageInfo,
                    ])
                    ->setFinishedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return [
                    'status' => 'failed',
                    'releaseVersion' => $release->getVersion(),
                    'package' => $packageInfo,
                    'steps' => $stepResults,
                    'orchestratorDispatch' => null,
                    'impactReport' => $execution->getImpactReport(),
                ];
            }
        }

        $execution
            ->setStatus('succeeded')
            ->setErrorMessage(null)
            ->setSummary([
                'message' => 'Atualizacao aplicada com sucesso.',
                'steps' => $stepResults,
                'package' => $packageInfo,
                'orchestratorDispatch' => $orchestratorDispatch,
            ])
            ->setFinishedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return [
            'status' => 'succeeded',
            'releaseVersion' => $release->getVersion(),
            'package' => $packageInfo,
            'steps' => $stepResults,
            'orchestratorDispatch' => $orchestratorDispatch,
            'impactReport' => $execution->getImpactReport(),
        ];
    }

    public function listExecutions(?string $targetSubscriberCode = null): array
    {
        $items = trim((string) $targetSubscriberCode) !== ''
            ? $this->executions->findRecentBySubscriber($targetSubscriberCode)
            : $this->executions->findRecent();

        return array_map(fn (SystemUpdateExecution $item): array => $this->formatExecution($item), $items);
    }

    public function listConsents(?string $targetSubscriberCode = null): array
    {
        return array_values(array_filter(
            array_map(fn (SystemUpdateConsent $item): array => $this->formatConsent($item), $this->consents->findRecent()),
            static fn (array $item): bool => trim((string) $targetSubscriberCode) === ''
                ? true
                : (string) ($item['targetSubscriberCode'] ?? '') === trim((string) $targetSubscriberCode)
        ));
    }

    public function listJobs(): array
    {
        return array_map(function ($job): array {
            return [
                'id' => $job->getId(),
                'status' => $job->getStatus(),
                'jobType' => $job->getJobType(),
                'recordId' => $job->getRecordId(),
                'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
                'updatedAt' => $job->getUpdatedAt()->format(DATE_ATOM),
                'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
                'result' => $job->getResult(),
                'lastError' => $job->getLastError(),
            ];
        }, $this->runtimeJobs->findRecentByJobType('system.update.apply', 20));
    }

    public function getJob(int $jobId): array
    {
        $job = $this->runtimeJobs->find($jobId);
        if (!$job || $job->getJobType() !== 'system.update.apply') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_JOB_NOT_FOUND', 'Job de atualizacao nao encontrado.', 404, ['jobId' => $jobId]);
        }

        return [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'jobType' => $job->getJobType(),
            'recordId' => $job->getRecordId(),
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $job->getUpdatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
            'result' => $job->getResult(),
            'lastError' => $job->getLastError(),
        ];
    }

    public function runtimeSummary(bool $autoQueue = true): array
    {
        $result = $this->check(null, true, $autoQueue);
        $summary = $result['summary'];
        $criticalPending = array_values(array_map(
            static fn (array $item): string => (string) ($item['version'] ?? ''),
            array_filter((array) ($result['releases'] ?? []), static fn (array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['severity'] ?? '') === 'critical')
        ));
        $summary['criticalActionRequired'] = count($criticalPending) > 0;
        $summary['pendingCriticalVersions'] = $criticalPending;
        $summary['recommendedScreenId'] = 'admin.atualizacoes';

        return $summary;
    }

    public function registerConsent(string $version, string $status = 'approved', ?string $reason = null, string $source = 'ui', ?string $targetSubscriberCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $environment = $this->environmentIdentity->resolve();
        $normalizedStatus = $this->normalizeConsentStatus($status);
        $consent = (new SystemUpdateConsent())
            ->setReleaseVersion($release->getVersion())
            ->setStatus($normalizedStatus)
            ->setApprovedBy($this->permissions->getUserId() ?: 'system')
            ->setSource($source)
            ->setDeploymentMode($this->deploymentMode->resolve())
            ->setDatabaseIdentity((string) ($environment['databaseIdentity'] ?? 'db:dev'))
            ->setTargetSubscriberCode($targetSubscriber?->getCode())
            ->setTargetSubscriberName($targetSubscriber?->getName())
            ->setReason($reason);
        $this->entityManager->persist($consent);
        $this->entityManager->flush();

        return $this->formatConsent($consent);
    }

    public function buildRolloutPlan(string $version, ?string $targetSubscriberCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        $metadata = $release->getMetadata();
        $requiresMaintenance = ($metadata['requiresMaintenanceMode'] ?? false) === true || in_array('migrate', $release->getSteps(), true);
        $requiresBackup = ($metadata['requiresBackup'] ?? false) === true || in_array('migrate', $release->getSteps(), true);

        return [
            'version' => $release->getVersion(),
            'title' => $release->getTitle(),
            'deploymentMode' => $this->deploymentMode->resolve(),
            'targetSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'requiresMaintenanceMode' => $requiresMaintenance,
            'requiresBackup' => $requiresBackup,
            'orchestratorAction' => (string) ($metadata['orchestratorAction'] ?? 'rolling-restart'),
            'steps' => $release->getSteps(),
            'consentStatus' => $evaluation['consentStatus'] ?? 'not-required',
            'impactReport' => $evaluation['impactReport'] ?? [],
            'suggestedSequence' => [
                'validar manifesto e assinatura',
                $requiresBackup ? 'executar backup antes da aplicacao' : 'backup opcional conforme politica do ambiente',
                $requiresMaintenance ? 'abrir janela de manutencao antes do rollout' : 'manter rollout sem parada longa',
                'rodar app:update:apply ' . $release->getVersion() . ' ou enfileirar pela UI',
                'executar app:integrity:monitor --fail-on-invalid ao final',
            ],
        ];
    }

    public function runPending(?string $source = null, bool $autoOnly = false, bool $allowConsented = true): array
    {
        $check = $this->check($source, true, false);
        $applied = [];
        foreach ($check['releases'] as $release) {
            if (($release['status'] ?? '') !== 'pending') {
                continue;
            }
            $canAuto = ($release['autoApplicable'] ?? false) === true;
            $canConsented = $allowConsented && ($release['consentApproved'] ?? false) === true;
            if ($autoOnly && !$canAuto) {
                continue;
            }
            if (!$canAuto && !$canConsented) {
                continue;
            }
            $applied[] = $this->applyRelease(
                (string) $release['version'],
                null,
                true,
                $canAuto ? 'auto' : 'consented',
                'runner'
            );
        }

        return [
            'applied' => $applied,
            'summary' => $this->check($source, true, false)['summary'],
        ];
    }

    public function subscriberLogBootstrap(?string $targetSubscriberCode = null, int $limit = 50): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $items = trim((string) $targetSubscriberCode) !== ''
            ? $this->executions->findRecentBySubscriber($targetSubscriberCode, $limit)
            : $this->executions->findRecentBySubscriber(null, $limit);

        return [
            'centralControl' => $this->central->resolve(),
            'subscribers' => $this->listTargetSubscribers(),
            'selectedSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'executions' => array_map(fn (SystemUpdateExecution $item): array => $this->formatExecution($item), $items),
        ];
    }

    public function listExecutionHistory(array $filters = []): array
    {
        $subscriberCode = trim((string) ($filters['subscriberCode'] ?? '')) ?: null;
        $items = $subscriberCode !== null
            ? $this->executions->findRecentBySubscriber($subscriberCode, max(1, min(300, (int) ($filters['limit'] ?? 120))))
            : $this->executions->findRecent(max(1, min(300, (int) ($filters['limit'] ?? 120))));

        $status = trim((string) ($filters['status'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $dateFrom = trim((string) ($filters['dateFrom'] ?? ''));
        $dateTo = trim((string) ($filters['dateTo'] ?? ''));

        $rows = array_values(array_filter(array_map(fn (SystemUpdateExecution $item): array => $this->formatExecution($item), $items), static function (array $row) use ($status, $category, $dateFrom, $dateTo): bool {
            if ($status !== '' && (string) ($row['status'] ?? '') !== $status) {
                return false;
            }
            if ($category !== '' && (string) ($row['category'] ?? '') !== $category) {
                return false;
            }
            $createdAt = (string) ($row['createdAt'] ?? '');
            if ($dateFrom !== '' && $createdAt !== '' && strcmp($createdAt, $dateFrom) < 0) {
                return false;
            }
            if ($dateTo !== '' && $createdAt !== '' && strcmp($createdAt, $dateTo . 'T99:99:99') > 0) {
                return false;
            }
            return true;
        }));

        return [
            'items' => $rows,
            'summary' => [
                'total' => count($rows),
                'succeeded' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'succeeded')),
                'failed' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'failed')),
                'queued' => count(array_filter($rows, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['queued', 'running'], true))),
            ],
        ];
    }

    private function evaluateReleaseEntity(SystemUpdateRelease $release, ?string $targetSubscriberCode = null): array
    {
        return $this->evaluateRelease([
            'version' => $release->getVersion(),
            'title' => $release->getTitle(),
            'category' => $release->getCategory(),
            'severity' => $release->getSeverity(),
            'description' => $release->getDescription(),
            'autoApplySaas' => $release->isAutoApplySaas(),
            'autoApplyOnPrem' => $release->isAutoApplyOnPrem(),
            'requiresSubscriberConsent' => $release->isRequiresSubscriberConsent(),
            'blocksNextUpdates' => $release->isBlocksNextUpdates(),
            'internetRequired' => $release->isInternetRequired(),
            'requiresVersionMin' => $release->getRequiresVersionMin(),
            'requiresAppliedUpdates' => $release->getRequiresAppliedUpdates(),
            'steps' => $release->getSteps(),
            'programUpdates' => $release->getProgramUpdates(),
            'metadata' => $release->getMetadata(),
            'manifestSource' => $release->getManifestSource(),
            'manifestHash' => $release->getManifestHash(),
            'publishedAt' => $release->getPublishedAt()?->format(DATE_ATOM),
        ], $this->resolveCurrentVersion(), $this->resolveAppliedVersions(), $this->deploymentMode->resolve(), true, '', $targetSubscriberCode);
    }

    private function normalizeRelease(array $payload, string $source, string $hash): array
    {
        $publishedAt = trim((string) ($payload['publishedAt'] ?? ''));
        return [
            'version' => trim((string) ($payload['version'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? 'Atualizacao sem titulo')),
            'category' => $this->normalizeCategory((string) ($payload['category'] ?? 'recommended')),
            'severity' => $this->normalizeSeverity((string) ($payload['severity'] ?? 'medium')),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'autoApplySaas' => ($payload['autoApplySaas'] ?? false) === true,
            'autoApplyOnPrem' => ($payload['autoApplyOnPrem'] ?? false) === true,
            'requiresSubscriberConsent' => ($payload['requiresSubscriberConsent'] ?? true) !== false,
            'blocksNextUpdates' => ($payload['blocksNextUpdates'] ?? false) === true,
            'internetRequired' => ($payload['internetRequired'] ?? false) === true,
            'requiresVersionMin' => trim((string) ($payload['requiresVersionMin'] ?? '')) ?: null,
            'requiresAppliedUpdates' => array_values(array_filter(array_map('strval', (array) ($payload['requiresAppliedUpdates'] ?? [])))),
            'steps' => array_values(array_filter(array_map('strval', (array) ($payload['steps'] ?? [])))),
            'programUpdates' => array_values(array_filter((array) ($payload['programUpdates'] ?? []), 'is_array')),
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            'manifestSource' => $source,
            'manifestHash' => $hash,
            'publishedAt' => $publishedAt !== '' ? $publishedAt : null,
        ];
    }

    private function hydrateRelease(SystemUpdateRelease $release, array $data): void
    {
        $release
            ->setVersion((string) $data['version'])
            ->setTitle((string) $data['title'])
            ->setCategory((string) $data['category'])
            ->setSeverity((string) $data['severity'])
            ->setDescription($data['description'] ?? null)
            ->setAutoApplySaas(($data['autoApplySaas'] ?? false) === true)
            ->setAutoApplyOnPrem(($data['autoApplyOnPrem'] ?? false) === true)
            ->setRequiresSubscriberConsent(($data['requiresSubscriberConsent'] ?? true) !== false)
            ->setBlocksNextUpdates(($data['blocksNextUpdates'] ?? false) === true)
            ->setInternetRequired(($data['internetRequired'] ?? false) === true)
            ->setRequiresVersionMin($data['requiresVersionMin'] ?? null)
            ->setRequiresAppliedUpdates((array) ($data['requiresAppliedUpdates'] ?? []))
            ->setSteps((array) ($data['steps'] ?? []))
            ->setProgramUpdates((array) ($data['programUpdates'] ?? []))
            ->setMetadata((array) ($data['metadata'] ?? []))
            ->setManifestSource($data['manifestSource'] ?? null)
            ->setManifestHash($data['manifestHash'] ?? null)
            ->setCheckedAt(new \DateTimeImmutable());

        $publishedAt = $data['publishedAt'] ?? null;
        $release->setPublishedAt($publishedAt ? new \DateTimeImmutable((string) $publishedAt) : null);
    }

    private function resolveCurrentVersion(): string
    {
        return $this->executions->findLatestSuccessfulVersion() ?? $this->systemVersion->resolve();
    }

    /**
     * @return list<string>
     */
    private function resolveAppliedVersions(): array
    {
        $items = [];
        foreach ($this->executions->findRecent(200) as $execution) {
            if ($execution->getStatus() !== 'succeeded') {
                continue;
            }
            $items[] = $execution->getReleaseVersion();
        }

        return array_values(array_unique($items));
    }

    private function evaluateRelease(array $release, string $currentVersion, array $appliedVersions, string $deploymentMode, bool $manifestTrusted, string $manifestMessage, ?string $targetSubscriberCode = null): array
    {
        $status = 'pending';
        $dependencyIssues = [];
        if (!$manifestTrusted) {
            $status = 'blocked_manifest';
            $dependencyIssues[] = $manifestMessage !== '' ? $manifestMessage : 'Manifesto sem confianca para aplicacao.';
        }
        $requiresVersionMin = trim((string) ($release['requiresVersionMin'] ?? ''));
        if ($requiresVersionMin !== '' && version_compare($currentVersion, $requiresVersionMin, '<')) {
            $status = 'blocked_dependency';
            $dependencyIssues[] = 'Versao minima atual exigida: ' . $requiresVersionMin . '.';
        }
        foreach ((array) ($release['requiresAppliedUpdates'] ?? []) as $requiredVersion) {
            if (!in_array((string) $requiredVersion, $appliedVersions, true)) {
                $status = 'blocked_dependency';
                $dependencyIssues[] = 'Atualizacao obrigatoria pendente: ' . $requiredVersion . '.';
            }
        }
        if (in_array((string) ($release['version'] ?? ''), $appliedVersions, true)) {
            $status = 'applied';
        }

        $requiresConsent = ($release['requiresSubscriberConsent'] ?? true) !== false;
        $autoApplicable = $status === 'pending' && $this->isAutoApplicable($release, $deploymentMode);
        $impactReport = $this->analyzeProgramCustomizationImpact((array) ($release['programUpdates'] ?? []), $targetSubscriberCode);
        if ($status === 'pending' && ($impactReport['blockingCustomizationCount'] ?? 0) > 0 && (($release['metadata']['blockOnCustomizationConflict'] ?? false) === true)) {
            $status = 'blocked_customization';
            $dependencyIssues[] = 'Existem customizacoes de assinante que exigem rebase antes desta release.';
        }
        $consent = $this->resolveConsent((string) ($release['version'] ?? ''), $targetSubscriberCode);
        $consentStatus = $requiresConsent ? ($consent?->getStatus() ?? 'pending') : 'not-required';
        $consentApproved = $requiresConsent ? ($consent && $consent->getStatus() === 'approved') : true;
        $packageUrl = trim((string) ($release['metadata']['packageUrl'] ?? ''));

        return [
            'version' => (string) ($release['version'] ?? ''),
            'title' => (string) ($release['title'] ?? ''),
            'category' => (string) ($release['category'] ?? 'recommended'),
            'severity' => (string) ($release['severity'] ?? 'medium'),
            'description' => $release['description'] ?? null,
            'status' => $status,
            'currentVersion' => $currentVersion,
            'requiresConsent' => $requiresConsent,
            'consentStatus' => $consentStatus,
            'consentApproved' => $consentApproved,
            'consentInfo' => $consent ? $this->formatConsent($consent) : null,
            'autoApplicable' => $autoApplicable,
            'blocksNextUpdates' => ($release['blocksNextUpdates'] ?? false) === true,
            'dependencyIssues' => $dependencyIssues,
            'steps' => array_values((array) ($release['steps'] ?? [])),
            'manifestSource' => $release['manifestSource'] ?? null,
            'metadata' => is_array($release['metadata'] ?? null) ? $release['metadata'] : [],
            'impactReport' => $impactReport,
            'targetSubscriberCode' => $targetSubscriberCode,
            'programUpdates' => array_values((array) ($release['programUpdates'] ?? [])),
            'packageAvailable' => $packageUrl !== '',
            'packageUrl' => $packageUrl !== '' ? $packageUrl : null,
            'orchestratorEnabled' => $this->orchestrator->isEnabled(),
            'orchestratorEndpoint' => $this->orchestrator->getEndpoint(),
        ];
    }

    private function queueAutomaticRelease(array $items, string $deploymentMode): ?array
    {
        foreach ($items as $item) {
            if (($item['status'] ?? '') !== 'pending' || ($item['autoApplicable'] ?? false) !== true) {
                continue;
            }
            if ($this->executions->hasExecutionInStatuses((string) ($item['version'] ?? ''), ['queued', 'running', 'succeeded'])) {
                return null;
            }
            $result = $this->queueApply((string) $item['version'], true, 'auto', 'open', null);
            return [
                'version' => $item['version'],
                'deploymentMode' => $deploymentMode,
                'jobId' => $result['job']['id'] ?? null,
            ];
        }

        return null;
    }

    private function isAutoApplicable(array $release, string $deploymentMode): bool
    {
        if (($release['requiresSubscriberConsent'] ?? true) !== false) {
            return false;
        }

        return match ($deploymentMode) {
            'saas' => ($release['autoApplySaas'] ?? false) === true,
            'onprem' => ($release['autoApplyOnPrem'] ?? false) === true,
            default => false,
        };
    }

    private function isManifestTrusted(string $signatureStatus): bool
    {
        return in_array($signatureStatus, ['verified', 'local-unsigned'], true);
    }

    private function requireRelease(string $version): SystemUpdateRelease
    {
        $release = $this->releases->findOneByVersion($version);
        if (!$release) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_RELEASE_NOT_FOUND', 'Release de atualizacao nao encontrada.', 404, [
                'version' => $version,
            ]);
        }

        return $release;
    }

    private function newExecution(SystemUpdateRelease $release, string $mode, string $source, array $impactReport, ?AuthSubscriber $targetSubscriber = null): SystemUpdateExecution
    {
        $environment = $this->environmentIdentity->resolve();
        $targetSubscriberMetadata = $targetSubscriber ? $targetSubscriber->getMetadata() : [];
        $targetMetadata = is_array($targetSubscriberMetadata['provisioning'] ?? null) ? $targetSubscriberMetadata['provisioning'] : [];

        return (new SystemUpdateExecution())
            ->setReleaseVersion($release->getVersion())
            ->setReleaseTitle($release->getTitle())
            ->setCategory($release->getCategory())
            ->setSeverity($release->getSeverity())
            ->setMode($mode)
            ->setDeploymentMode($this->deploymentMode->resolve())
            ->setDatabaseEnvironment((string) ($environment['databaseEnvironment'] ?? 'dev'))
            ->setDatabaseIdentity((string) ($environment['databaseIdentity'] ?? 'db:dev'))
            ->setTargetSubscriberCode($targetSubscriber?->getCode())
            ->setTargetSubscriberName($targetSubscriber?->getName())
            ->setTargetDatabaseEnvironment((string) ($targetMetadata['databaseEnvironment'] ?? ''))
            ->setTargetDatabaseIdentity((string) ($targetMetadata['databaseIdentity'] ?? ''))
            ->setInitiatedBy($this->permissions->getUserId() ?: 'system')
            ->setInitiatedSource($source)
            ->setImpactReport($impactReport);
    }

    private function formatExecution(SystemUpdateExecution $execution): array
    {
        return [
            'id' => $execution->getId(),
            'releaseVersion' => $execution->getReleaseVersion(),
            'releaseTitle' => $execution->getReleaseTitle(),
            'category' => $execution->getCategory(),
            'severity' => $execution->getSeverity(),
            'status' => $execution->getStatus(),
            'mode' => $execution->getMode(),
            'deploymentMode' => $execution->getDeploymentMode(),
            'databaseEnvironment' => $execution->getDatabaseEnvironment(),
            'databaseIdentity' => $execution->getDatabaseIdentity(),
            'targetSubscriberCode' => $execution->getTargetSubscriberCode(),
            'targetSubscriberName' => $execution->getTargetSubscriberName(),
            'targetDatabaseEnvironment' => $execution->getTargetDatabaseEnvironment(),
            'targetDatabaseIdentity' => $execution->getTargetDatabaseIdentity(),
            'initiatedBy' => $execution->getInitiatedBy(),
            'initiatedSource' => $execution->getInitiatedSource(),
            'runtimeJobId' => $execution->getRuntimeJobId(),
            'summary' => $execution->getSummary(),
            'impactReport' => $execution->getImpactReport(),
            'errorMessage' => $execution->getErrorMessage(),
            'createdAt' => $execution->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $execution->getUpdatedAt()->format(DATE_ATOM),
            'finishedAt' => $execution->getFinishedAt()?->format(DATE_ATOM),
        ];
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = strtolower(trim($category));
        return in_array($normalized, ['security_critical', 'required_structural', 'recommended', 'optional_visual'], true) ? $normalized : 'recommended';
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        return in_array($normalized, ['critical', 'high', 'medium', 'low'], true) ? $normalized : 'medium';
    }

    private function normalizeConsentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, ['approved', 'rejected', 'revoked'], true) ? $normalized : 'approved';
    }

    private function releaseToArray(SystemUpdateRelease $release): array
    {
        return [
            'version' => $release->getVersion(),
            'title' => $release->getTitle(),
            'category' => $release->getCategory(),
            'severity' => $release->getSeverity(),
            'description' => $release->getDescription(),
            'autoApplySaas' => $release->isAutoApplySaas(),
            'autoApplyOnPrem' => $release->isAutoApplyOnPrem(),
            'requiresSubscriberConsent' => $release->isRequiresSubscriberConsent(),
            'blocksNextUpdates' => $release->isBlocksNextUpdates(),
            'internetRequired' => $release->isInternetRequired(),
            'requiresVersionMin' => $release->getRequiresVersionMin(),
            'requiresAppliedUpdates' => $release->getRequiresAppliedUpdates(),
            'steps' => $release->getSteps(),
            'programUpdates' => $release->getProgramUpdates(),
            'metadata' => $release->getMetadata(),
            'manifestSource' => $release->getManifestSource(),
            'manifestHash' => $release->getManifestHash(),
            'publishedAt' => $release->getPublishedAt()?->format(DATE_ATOM),
        ];
    }

    private function resolveReleasePayload(SystemUpdateRelease $release): array
    {
        $payload = $this->releaseToArray($release);
        try {
            $manifest = $this->manifestLoader->load();
            foreach ((array) ($manifest['releases'] ?? []) as $item) {
                if (!is_array($item) || (string) ($item['version'] ?? '') !== $release->getVersion()) {
                    continue;
                }
                return $this->normalizeRelease($item, (string) ($manifest['source'] ?? ''), (string) ($manifest['hash'] ?? ''));
            }
        } catch (\Throwable) {
        }

        return $payload;
    }

    private function resolveConsent(string $version, ?string $targetSubscriberCode = null): ?SystemUpdateConsent
    {
        return $this->consents->findLatestByVersionAndSubscriber($version, $targetSubscriberCode);
    }

    private function formatConsent(SystemUpdateConsent $consent): array
    {
        return [
            'id' => $consent->getId(),
            'releaseVersion' => $consent->getReleaseVersion(),
            'status' => $consent->getStatus(),
            'approvedBy' => $consent->getApprovedBy(),
            'source' => $consent->getSource(),
            'deploymentMode' => $consent->getDeploymentMode(),
            'databaseIdentity' => $consent->getDatabaseIdentity(),
            'targetSubscriberCode' => $consent->getTargetSubscriberCode(),
            'targetSubscriberName' => $consent->getTargetSubscriberName(),
            'reason' => $consent->getReason(),
            'createdAt' => $consent->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function analyzeProgramCustomizationImpact(array $programUpdates, ?string $targetSubscriberCode = null): array
    {
        $items = [];
        foreach ($programUpdates as $programUpdate) {
            $programCode = trim((string) ($programUpdate['programCode'] ?? ''));
            if ($programCode === '') {
                continue;
            }
            $published = $this->programVersions->findPublishedByProgramCode($programCode);
            $overlays = $this->overlays->findBy(['programCode' => $programCode], ['updatedAt' => 'DESC', 'id' => 'DESC']);
            $overlayItems = [];
            foreach ($overlays as $overlay) {
                if (!$overlay instanceof BuilderProgramOverlay || $overlay->getStatus() !== 'published') {
                    continue;
                }
                if ($targetSubscriberCode !== null && trim($targetSubscriberCode) !== '' && (string) $overlay->getSubscriberId() !== trim($targetSubscriberCode)) {
                    continue;
                }
                $overlayItems[] = $this->analyzeOverlayImpact($overlay);
            }
            $items[] = [
                'programCode' => $programCode,
                'targetPublishedVersion' => (string) ($programUpdate['targetPublishedVersion'] ?? ''),
                'policy' => (string) ($programUpdate['policy'] ?? 'respect_customizations'),
                'currentPublishedVersion' => $published?->getVersion(),
                'overlayCount' => count($overlayItems),
                'overlayImpacts' => $overlayItems,
            ];
        }

        return [
            'programs' => $items,
            'blockingCustomizationCount' => array_sum(array_map(static function (array $program): int {
                return count(array_filter((array) ($program['overlayImpacts'] ?? []), static fn (array $impact): bool => in_array((string) ($impact['status'] ?? ''), ['rebase_blocked', 'custom_frozen'], true)));
            }, $items)),
        ];
    }

    private function analyzeOverlayImpact(BuilderProgramOverlay $overlay): array
    {
        if ($overlay->getCustomizationKind() === 'customer_custom') {
            return [
                'overlayId' => $overlay->getId(),
                'subscriberId' => $overlay->getSubscriberId(),
                'customizationKind' => $overlay->getCustomizationKind(),
                'status' => 'custom_frozen',
                'message' => 'Variante completa do cliente permanece congelada e nao e sobrescrita pelo atualizador.',
                'upgradeFrozen' => $overlay->isUpgradeFrozen(),
            ];
        }

        $publishedVersion = $overlay->getId() ? $this->overlayVersions->findPublishedByOverlayId((int) $overlay->getId()) : null;
        if (!$publishedVersion || !$publishedVersion->getId()) {
            return [
                'overlayId' => $overlay->getId(),
                'subscriberId' => $overlay->getSubscriberId(),
                'customizationKind' => $overlay->getCustomizationKind(),
                'status' => 'missing_published_overlay',
                'message' => 'Overlay sem versao publicada para avaliar rebase.',
            ];
        }

        try {
            $preview = $this->overlayService->previewRebaseVersion((int) $publishedVersion->getId());
            $status = match ((string) ($preview['status'] ?? 'ok')) {
                'warning' => 'rebase_warning',
                'blocked' => 'rebase_blocked',
                default => 'rebase_ok',
            };

            return [
                'overlayId' => $overlay->getId(),
                'subscriberId' => $overlay->getSubscriberId(),
                'customizationKind' => $overlay->getCustomizationKind(),
                'status' => $status,
                'message' => (string) ($preview['reason'] ?? ''),
                'targetBaseVersion' => $preview['targetBaseVersion'] ?? null,
                'currentBaseVersion' => $preview['currentBaseVersion'] ?? null,
                'requiresConfirmation' => ($preview['requiresConfirmation'] ?? false) === true,
            ];
        } catch (\Throwable $error) {
            return [
                'overlayId' => $overlay->getId(),
                'subscriberId' => $overlay->getSubscriberId(),
                'customizationKind' => $overlay->getCustomizationKind(),
                'status' => 'rebase_blocked',
                'message' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return list<array{code: string, name: string, document: ?string, databaseEnvironment: string, databaseIdentity: string}>
     */
    private function listTargetSubscribers(): array
    {
        if (!$this->central->isCentralControl()) {
            return [];
        }

        return array_map(fn (AuthSubscriber $subscriber): array => $this->formatTargetSubscriber($subscriber), $this->subscribers->findEnabledOrdered());
    }

    private function formatTargetSubscriber(AuthSubscriber $subscriber): array
    {
        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];

        return [
            'code' => $subscriber->getCode(),
            'name' => $subscriber->getName(),
            'document' => $subscriber->getDocument(),
            'databaseEnvironment' => (string) ($provisioning['databaseEnvironment'] ?? ''),
            'databaseIdentity' => (string) ($provisioning['databaseIdentity'] ?? ''),
        ];
    }

    private function resolveTargetSubscriber(?string $targetSubscriberCode, bool $required): ?AuthSubscriber
    {
        $normalized = trim((string) $targetSubscriberCode);
        if ($normalized === '') {
            if ($required) {
                throw new RuntimeHttpException('SYSTEM_UPDATE_SUBSCRIBER_REQUIRED', 'Selecione o assinante alvo da atualizacao.', 422);
            }

            return null;
        }

        $subscriber = $this->subscribers->findEnabledByCode($normalized) ?? $this->subscribers->findOneBy(['code' => $normalized]);
        if (!$subscriber) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_SUBSCRIBER_NOT_FOUND', 'Assinante alvo da atualizacao nao encontrado.', 404, [
                'subscriberCode' => $normalized,
            ]);
        }

        return $subscriber;
    }

    private function shouldDispatchRollout(SystemUpdateRelease $release): bool
    {
        if ($this->deploymentMode->resolve() !== 'saas') {
            return false;
        }
        if (!$this->central->isCentralControl()) {
            return false;
        }

        $metadata = $release->getMetadata();
        if (($metadata['orchestratorDispatchEnabled'] ?? true) !== true) {
            return false;
        }

        return $this->orchestrator->isEnabled();
    }

    private function buildOrchestratorPayload(SystemUpdateRelease $release, array $evaluation, ?AuthSubscriber $targetSubscriber = null, ?array $package = null, ?SystemUpdateExecution $execution = null): array
    {
        $environment = $this->environmentIdentity->resolve();
        $metadata = $release->getMetadata();

        return [
            'event' => 'system.update.rollout',
            'releaseVersion' => $release->getVersion(),
            'releaseTitle' => $release->getTitle(),
            'category' => $release->getCategory(),
            'severity' => $release->getSeverity(),
            'deploymentMode' => $this->deploymentMode->resolve(),
            'databaseEnvironment' => (string) ($environment['databaseEnvironment'] ?? 'dev'),
            'databaseIdentity' => (string) ($environment['databaseIdentity'] ?? 'db:dev'),
            'targetSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'executionId' => $execution?->getId(),
            'orchestratorAction' => (string) ($metadata['orchestratorAction'] ?? 'rolling-restart'),
            'requiresMaintenanceMode' => ($metadata['requiresMaintenanceMode'] ?? false) === true,
            'requiresBackup' => ($metadata['requiresBackup'] ?? false) === true,
            'steps' => $release->getSteps(),
            'package' => $package,
            'impactReport' => $evaluation['impactReport'] ?? [],
        ];
    }
}
