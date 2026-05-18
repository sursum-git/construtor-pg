<?php

namespace App\Runtime;

use App\Entity\BuilderProgramOverlay;
use App\Entity\AuthSubscriber;
use App\Entity\SystemUpdateConsent;
use App\Entity\SystemUpdateExecution;
use App\Entity\SystemUpdateRelease;
use App\Entity\SystemUpdateTenantActivation;
use App\Repository\AuthSubscriberRepository;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Repository\SystemUpdateConsentRepository;
use App\Repository\SystemUpdateExecutionRepository;
use App\Repository\SystemUpdateReleaseRepository;
use App\Repository\SystemUpdateTenantActivationRepository;
use Doctrine\ORM\EntityManagerInterface;

class SystemUpdateService
{
    public function __construct(
        private readonly SystemUpdateManifestLoader $manifestLoader,
        private readonly SystemUpdateReleaseRepository $releases,
        private readonly SystemUpdateConsentRepository $consents,
        private readonly SystemUpdateTenantActivationRepository $activations,
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
        private readonly SystemUpdateManifestRulesValidator $manifestRules,
    ) {
    }

    public function bootstrap(bool $autoQueue = false, ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $check = $this->check(null, true, $autoQueue, $targetSubscriber?->getCode());

        return [
            'centralControl' => $this->central->resolve(),
            'summary' => $check['summary'],
            'operationalAlerts' => $check['summary']['operationalAlerts'] ?? [],
            'delayDashboard' => $check['summary']['delayDashboard'] ?? [],
            'releases' => $check['releases'],
            'subscribers' => $this->listTargetSubscribers(),
            'selectedSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'consents' => $this->listConsents($targetSubscriber?->getCode()),
            'activations' => $this->listActivations($targetSubscriber?->getCode()),
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
        $this->manifestRules->assertValid($normalized);

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
        $targetSubscriberKey = $targetSubscriber?->getCode();
        $currentVersion = $this->resolveCurrentVersion($targetSubscriberKey);
        $appliedVersions = $this->resolveAppliedVersions($targetSubscriberKey);
        $satisfiedVersions = $this->resolveSatisfiedVersions($normalized, $appliedVersions);

        $items = [];
        $manifestTrusted = $this->isManifestTrusted((string) ($manifest['signatureStatus'] ?? 'unknown'));
        foreach ($normalized as $releaseData) {
            $items[] = $this->evaluateRelease($releaseData, $currentVersion, $appliedVersions, $satisfiedVersions, $deploymentMode, $manifestTrusted, (string) ($manifest['signatureMessage'] ?? ''), $targetSubscriberKey);
        }

        $autoQueued = null;
        if ($autoQueue && $manifestTrusted) {
            $autoQueued = $this->queueAutomaticRelease($items, $deploymentMode);
        }

        $delayDashboard = $this->buildDelayDashboard($normalized, $targetSubscriber?->getCode());
        $operationalAlerts = $this->buildOperationalAlerts($items, $delayDashboard, $targetSubscriber?->getCode());

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
                'delayDashboard' => $delayDashboard,
                'operationalAlerts' => $operationalAlerts,
            ],
            'releases' => $items,
        ];
    }

    public function queueApply(string $version, bool $forceConsent = false, string $mode = 'manual', string $source = 'ui', ?string $targetSubscriberCode = null): array
    {
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $release = $this->requireRelease($version);
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        $precheck = $this->buildCompatibilityPrecheck($this->resolveReleasePayload($release), $evaluation, $targetSubscriber?->getCode());
        if (($precheck['blockingCount'] ?? 0) > 0) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_PRECHECK_FAILED', 'A release falhou no pre-check de compatibilidade.', 422, $precheck);
        }
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
            'precheck' => $precheck,
        ];
    }

    public function simulateRelease(string $version, ?string $targetSubscriberCode = null, ?string $batchCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $payload = $this->resolveReleasePayload($release);
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        $precheck = $this->buildCompatibilityPrecheck($payload, $evaluation, $targetSubscriber?->getCode());
        $subscriberImpact = $this->buildSubscriberImpactReport($payload, $targetSubscriber?->getCode(), $batchCode);
        $rollbackPlan = $this->buildRollbackPlan($release, $targetSubscriber?->getCode());

        return [
            'release' => $evaluation,
            'precheck' => $precheck,
            'subscriberImpact' => $subscriberImpact,
            'rollbackPlan' => $rollbackPlan,
            'delayDashboard' => $this->buildDelayDashboard([$payload], $targetSubscriber?->getCode()),
            'operationalAlerts' => $this->buildOperationalAlerts([$evaluation], $this->buildDelayDashboard([$payload], $targetSubscriber?->getCode()), $targetSubscriber?->getCode()),
        ];
    }

    public function rollbackRelease(string $version, ?string $reason = null, ?string $targetSubscriberCode = null, ?string $targetVersion = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $plan = $this->buildRollbackPlan($release, $targetSubscriber?->getCode(), $targetVersion);
        if (($plan['supported'] ?? false) !== true) {
            throw new RuntimeHttpException('SYSTEM_UPDATE_ROLLBACK_UNSUPPORTED', 'A release nao possui rollback operacional suportado.', 422, $plan);
        }

        $execution = $this->newExecution($release, 'rollback', 'ui', [
            'rollbackPlan' => $plan,
        ], $targetSubscriber);
        $execution
            ->setStatus('running')
            ->setSummary([
                'message' => 'Rollback em execucao.',
                'rollback' => $plan,
                'reason' => $reason,
            ]);
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        $stepResults = [];
        foreach ((array) ($plan['steps'] ?? []) as $step) {
            $result = $this->steps->run($step);
            $stepResults[] = $result;
            if (($result['status'] ?? '') !== 'ok') {
                $execution
                    ->setStatus('failed')
                    ->setErrorMessage('Falha ao executar o passo de rollback ' . (string) ($result['step'] ?? ''))
                    ->setSummary([
                        'message' => 'Rollback falhou.',
                        'rollback' => $plan,
                        'reason' => $reason,
                        'steps' => $stepResults,
                    ])
                    ->setFinishedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return [
                    'status' => 'failed',
                    'releaseVersion' => $release->getVersion(),
                    'execution' => $this->formatExecution($execution),
                    'rollbackPlan' => $plan,
                    'steps' => $stepResults,
                ];
            }
        }

        $dispatch = null;
        if (($plan['dispatchRollback'] ?? false) === true) {
            $dispatch = $this->orchestrator->dispatch(array_merge(
                $this->buildOrchestratorPayload($release, $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode()), $targetSubscriber, null, $execution),
                [
                    'event' => 'system.update.rollback',
                    'orchestratorAction' => 'rollback',
                    'rollbackTargetVersion' => $plan['targetVersion'] ?? null,
                    'rollbackReason' => $reason,
                    'rollback' => true,
                ]
            ));
        }

        $execution
            ->setStatus('succeeded')
            ->setErrorMessage(null)
            ->setSummary([
                'message' => 'Rollback concluido.',
                'rollback' => $plan,
                'reason' => $reason,
                'steps' => $stepResults,
                'orchestratorDispatch' => $dispatch,
            ])
            ->setFinishedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return [
            'status' => 'succeeded',
            'releaseVersion' => $release->getVersion(),
            'execution' => $this->formatExecution($execution),
            'rollbackPlan' => $plan,
            'steps' => $stepResults,
            'orchestratorDispatch' => $dispatch,
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

    public function dispatchRollout(string $version, ?string $targetSubscriberCode = null, ?string $batchCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        $plan = $this->buildRolloutPlan($version, $targetSubscriber?->getCode());
        $resolvedRelease = $this->resolveReleasePayload($release);
        $package = null;
        if (trim((string) (($resolvedRelease['metadata']['packageUrl'] ?? ''))) !== '') {
            $package = $this->packages->download($resolvedRelease);
        }
        $dispatches = [];
        if ($targetSubscriber) {
            $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber->getCode());
            $dispatches[] = $this->dispatchRolloutForSubscriber($release, $evaluation, $targetSubscriber, $package, $plan, null);
        } else {
            $batchCode = trim((string) ($batchCode ?: ($plan['defaultBatchCode'] ?? '')));
            $selectedBatch = null;
            foreach ((array) ($plan['rolloutBatches'] ?? []) as $batch) {
                if ((string) ($batch['code'] ?? '') === $batchCode) {
                    $selectedBatch = $batch;
                    break;
                }
            }
            if (!is_array($selectedBatch)) {
                throw new RuntimeHttpException('SYSTEM_UPDATE_BATCH_REQUIRED', 'Selecione um lote SaaS antes de despachar o rollout.', 422, [
                    'availableBatches' => $plan['rolloutBatches'] ?? [],
                ]);
            }
            foreach ((array) ($selectedBatch['subscribers'] ?? []) as $subscriberRow) {
                $subscriberCode = trim((string) ($subscriberRow['code'] ?? ''));
                if ($subscriberCode === '') {
                    continue;
                }
                $subscriber = $this->resolveTargetSubscriber($subscriberCode, true);
                $evaluation = $this->evaluateReleaseEntity($release, $subscriber->getCode());
                $dispatches[] = $this->dispatchRolloutForSubscriber($release, $evaluation, $subscriber, $package, $plan, $selectedBatch);
            }
        }

        return [
            'releaseVersion' => $release->getVersion(),
            'targetSubscriber' => $targetSubscriber ? $this->formatTargetSubscriber($targetSubscriber) : null,
            'plan' => $plan,
            'dispatches' => $dispatches,
            'summary' => [
                'dispatchCount' => count($dispatches),
                'succeededCount' => count(array_filter($dispatches, static fn (array $item): bool => (string) (($item['dispatch']['status'] ?? '')) === 'dispatched')),
                'failedCount' => count(array_filter($dispatches, static fn (array $item): bool => (string) (($item['dispatch']['status'] ?? '')) !== 'dispatched')),
            ],
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
            $result = $this->steps->run($step);
            $stepResults[] = $result;
            if (($result['status'] ?? '') !== 'ok') {
                $execution
                    ->setStatus('failed')
                    ->setErrorMessage('Falha ao executar o passo ' . (string) ($result['stepTitle'] ?? $result['step'] ?? '') . '.')
                    ->setSummary([
                        'message' => 'Falha na atualizacao.',
                        'failedStep' => $result['step'] ?? null,
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

        $pipelineImpactReport = $this->processOverlayUpdatePipeline(
            (array) ($evaluation['impactReport'] ?? []),
            $release->getVersion()
        );
        $execution->setImpactReport($pipelineImpactReport);
        $this->entityManager->flush();

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
                        'overlayPipeline' => $pipelineImpactReport['overlayPipelineSummary'] ?? [],
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
                'overlayPipeline' => $pipelineImpactReport['overlayPipelineSummary'] ?? [],
                'rolloutAudit' => [
                    'stage' => $orchestratorDispatch ? 'apply_and_dispatch' : 'apply_only',
                    'dispatchCount' => $orchestratorDispatch ? 1 : 0,
                    'entryAccessMode' => (($release->getMetadata()['saasBlockEntryUntilComplete'] ?? false) === true) ? 'blocked' : 'warning',
                    'windowStatus' => $this->resolveSaasRolloutWindow($release->getMetadata())['status'] ?? 'unscheduled',
                    'batchCode' => null,
                ],
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

    public function listActivations(?string $targetSubscriberCode = null): array
    {
        return array_values(array_filter(
            array_map(fn (SystemUpdateTenantActivation $item): array => $this->formatActivation($item), $this->activations->findRecent()),
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
        $deploymentMode = (string) ($summary['deploymentMode'] ?? $this->deploymentMode->resolve());
        $criticalMode = $deploymentMode === 'onprem' ? $this->resolveOnPremCriticalMode() : null;
        $criticalAccessPolicy = $deploymentMode === 'onprem' ? $this->resolveOnPremCriticalAccessPolicy() : 'warn';
        $criticalPending = array_values(array_map(
            static fn (array $item): string => (string) ($item['version'] ?? ''),
            array_filter((array) ($result['releases'] ?? []), static fn (array $item): bool => ($item['status'] ?? '') === 'pending' && ($item['severity'] ?? '') === 'critical')
        ));
        $summary['criticalActionRequired'] = count($criticalPending) > 0;
        $summary['pendingCriticalVersions'] = $criticalPending;
        $summary['recommendedScreenId'] = 'admin.atualizacoes';
        $summary['criticalPolicy'] = $criticalAccessPolicy;
        $summary['criticalMode'] = $criticalMode;
        $summary['canRunPendingLocally'] = $deploymentMode === 'onprem';
        $summary['canDownloadPendingLocally'] = $deploymentMode === 'onprem';
        $summary['runtimeRunPendingEndpoint'] = $deploymentMode === 'onprem' ? '/api/runtime/system-updates/run-pending' : null;
        $summary['runtimeDownloadPendingEndpoint'] = $deploymentMode === 'onprem' ? '/api/runtime/system-updates/download-pending-critical' : null;
        $summary['accessMode'] = 'ready';
        $summary['criticalActionTitle'] = '';
        $summary['criticalActionMessage'] = '';
        $summary['criticalActionLabel'] = '';
        $summary['criticalActionKind'] = '';
        if ($summary['criticalActionRequired'] === true) {
            $message = 'Existe atualizacao critica pendente: ' . implode(', ', $criticalPending) . '.';
            if ($deploymentMode === 'onprem') {
                if ($criticalMode === 'download_only') {
                    $summary['criticalActionKind'] = 'download';
                    $summary['criticalActionLabel'] = 'Baixar pacote critico';
                    $summary['criticalActionTitle'] = 'Atualizacao critica pendente';
                    $summary['criticalActionMessage'] = $message . ' O pacote deve ser baixado localmente antes da aplicacao.';
                } else {
                    $summary['criticalActionKind'] = 'run';
                    $summary['criticalActionLabel'] = $criticalMode === 'auto' ? 'Executar rotina automatica' : 'Executar atualizacao local';
                    $summary['criticalActionTitle'] = $criticalMode === 'auto' ? 'Atualizacao critica automatica' : 'Atualizacao critica pendente';
                    $summary['criticalActionMessage'] = $message . ' A politica atual do on-premise exige ' . $this->describeOnPremCriticalMode($criticalMode) . '.';
                }
            }
            if ($deploymentMode === 'onprem' && $criticalAccessPolicy === 'block') {
                $summary['accessMode'] = 'blocked';
            } else {
                $summary['accessMode'] = 'warning';
                if ($deploymentMode !== 'onprem') {
                    $summary['criticalActionTitle'] = 'Atualizacao critica pendente';
                    $summary['criticalActionMessage'] = $message . ' Revise a politica de atualizacao antes de continuar.';
                }
            }
            if (!empty($summary['autoQueuedRelease']['version'])) {
                $summary['criticalActionMessage'] .= ' A release ' . (string) $summary['autoQueuedRelease']['version'] . ' foi enfileirada automaticamente.';
            }
        }

        if ($deploymentMode === 'saas') {
            $rolloutState = $this->resolveSaasRolloutState();
            if ($rolloutState !== null) {
                $summary['saasRolloutState'] = $rolloutState;
                if (($rolloutState['active'] ?? false) === true) {
                    $summary['criticalActionRequired'] = true;
                    $summary['accessMode'] = (string) ($rolloutState['accessMode'] ?? 'warning');
                    $summary['criticalActionTitle'] = (string) ($rolloutState['title'] ?? 'Atualizacao SaaS em andamento');
                    $summary['criticalActionMessage'] = (string) ($rolloutState['message'] ?? 'Uma atualizacao SaaS esta em andamento para este ambiente.');
                    $summary['criticalActionLabel'] = 'Atualizacao em andamento';
                    $summary['criticalActionKind'] = 'wait';
                    $summary['canRunPendingLocally'] = false;
                    $summary['canDownloadPendingLocally'] = false;
                }
            }
        }

        return $summary;
    }

    public function runPendingRuntime(bool $autoOnly = true): array
    {
        if ($this->deploymentMode->resolve() !== 'onprem') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_RUNTIME_MODE_INVALID', 'A aplicacao local de updates runtime existe apenas no modo on-premise.', 422, [
                'deploymentMode' => $this->deploymentMode->resolve(),
            ]);
        }

        $mode = $this->resolveOnPremCriticalMode();
        if ($mode === 'download_only') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_RUNTIME_DOWNLOAD_ONLY', 'A politica atual permite apenas o download local do pacote critico.', 422, [
                'criticalMode' => $mode,
            ]);
        }

        $result = $this->runPending(null, $mode === 'auto' ? true : $autoOnly, true);
        $result['runtimeSummary'] = $this->runtimeSummary(false);

        return $result;
    }

    public function downloadPendingCriticalRuntime(): array
    {
        if ($this->deploymentMode->resolve() !== 'onprem') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_RUNTIME_MODE_INVALID', 'O download local de updates existe apenas no modo on-premise.', 422, [
                'deploymentMode' => $this->deploymentMode->resolve(),
            ]);
        }

        $check = $this->check(null, true, false);
        $selected = null;
        foreach ((array) ($check['releases'] ?? []) as $release) {
            if (($release['status'] ?? '') === 'pending' && ($release['severity'] ?? '') === 'critical') {
                $selected = $release;
                break;
            }
        }

        if (!$selected || trim((string) ($selected['version'] ?? '')) === '') {
            throw new RuntimeHttpException('SYSTEM_UPDATE_NO_PENDING_CRITICAL', 'Nao existe release critica pendente para download local.', 404, []);
        }

        $download = $this->downloadPackage((string) $selected['version']);

        return [
            'status' => 'downloaded',
            'releaseVersion' => (string) ($selected['version'] ?? ''),
            'package' => $download['package'] ?? null,
            'runtimeSummary' => $this->runtimeSummary(false),
        ];
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

    public function registerTenantActivation(string $version, string $status = 'enabled', ?string $reason = null, string $source = 'ui', ?string $targetSubscriberCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, true);
        $environment = $this->environmentIdentity->resolve();
        $activation = (new SystemUpdateTenantActivation())
            ->setReleaseVersion($release->getVersion())
            ->setStatus($this->normalizeActivationStatus($status))
            ->setDecidedBy($this->permissions->getUserId() ?: 'system')
            ->setSource($source)
            ->setDeploymentMode($this->deploymentMode->resolve())
            ->setDatabaseIdentity((string) ($environment['databaseIdentity'] ?? 'db:dev'))
            ->setTargetSubscriberCode($targetSubscriber?->getCode() ?: '')
            ->setTargetSubscriberName($targetSubscriber?->getName())
            ->setReason($reason);
        $this->entityManager->persist($activation);
        $this->entityManager->flush();

        return $this->formatActivation($activation);
    }

    public function buildRolloutPlan(string $version, ?string $targetSubscriberCode = null): array
    {
        $release = $this->requireRelease($version);
        $targetSubscriber = $this->resolveTargetSubscriber($targetSubscriberCode, $this->central->isCentralControl());
        $evaluation = $this->evaluateReleaseEntity($release, $targetSubscriber?->getCode());
        $metadata = $release->getMetadata();
        $stepCodes = array_values(array_filter(array_map(static function ($step): string {
            return trim((string) (is_array($step) ? ($step['code'] ?? '') : $step));
        }, $release->getSteps()), static fn (string $value): bool => $value !== ''));
        $requiresMaintenance = ($metadata['requiresMaintenanceMode'] ?? false) === true || in_array('migrate', $stepCodes, true);
        $requiresBackup = ($metadata['requiresBackup'] ?? false) === true || in_array('migrate', $stepCodes, true);
        $rolloutWindow = $this->resolveSaasRolloutWindow($metadata);
        $rolloutBatches = $this->resolveSaasRolloutBatches($release, $targetSubscriber?->getCode());
        $entryBlockPlan = $this->buildSaasEntryBlockPlan($release, $rolloutWindow);

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
            'rolloutWindow' => $rolloutWindow,
            'rolloutBatches' => $rolloutBatches,
            'defaultBatchCode' => $rolloutBatches[0]['code'] ?? null,
            'entryBlockPlan' => $entryBlockPlan,
            'rollbackPlan' => $this->buildRollbackPlan($release, $targetSubscriber?->getCode()),
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

        $byStatus = [];
        $byCategory = [];
        $overlayPipeline = [
            'draftCreated' => 0,
            'draftExists' => 0,
            'reviewRequired' => 0,
            'blocked' => 0,
            'frozen' => 0,
            'missingVersion' => 0,
            'pipelineFailed' => 0,
        ];
        $rolloutAudit = [
            'dispatchCount' => 0,
            'blockedEntryCount' => 0,
            'windowScheduledCount' => 0,
            'batchCodes' => [],
            'byStage' => [],
        ];
        $timeline = [];
        foreach ($rows as $row) {
            $rowStatus = (string) ($row['status'] ?? '');
            $rowCategory = (string) ($row['category'] ?? '');
            if ($rowStatus !== '') {
                $byStatus[$rowStatus] = (int) ($byStatus[$rowStatus] ?? 0) + 1;
            }
            if ($rowCategory !== '') {
                $byCategory[$rowCategory] = (int) ($byCategory[$rowCategory] ?? 0) + 1;
            }
            $pipelineSummary = is_array($row['summary']['overlayPipeline'] ?? null)
                ? $row['summary']['overlayPipeline']
                : (is_array($row['impactReport']['overlayPipelineSummary'] ?? null) ? $row['impactReport']['overlayPipelineSummary'] : []);
            $overlayPipeline['draftCreated'] += (int) ($pipelineSummary['draftCreated'] ?? 0);
            $overlayPipeline['draftExists'] += (int) ($pipelineSummary['draftExists'] ?? 0);
            $overlayPipeline['reviewRequired'] += (int) ($pipelineSummary['reviewRequired'] ?? 0);
            $overlayPipeline['blocked'] += (int) ($pipelineSummary['blocked'] ?? 0);
            $overlayPipeline['frozen'] += (int) ($pipelineSummary['frozen'] ?? 0);
            $overlayPipeline['missingVersion'] += (int) ($pipelineSummary['missingVersion'] ?? 0);
            $overlayPipeline['pipelineFailed'] += (int) ($pipelineSummary['pipelineFailed'] ?? 0);
            $rolloutInfo = is_array($row['summary']['rolloutAudit'] ?? null) ? $row['summary']['rolloutAudit'] : [];
            if ($rolloutInfo) {
                $rolloutAudit['dispatchCount'] += (int) ($rolloutInfo['dispatchCount'] ?? 0);
                $rolloutAudit['blockedEntryCount'] += (($rolloutInfo['entryAccessMode'] ?? '') === 'blocked') ? 1 : 0;
                $rolloutAudit['windowScheduledCount'] += (($rolloutInfo['windowStatus'] ?? '') === 'scheduled') ? 1 : 0;
                $batchCode = trim((string) ($rolloutInfo['batchCode'] ?? ''));
                if ($batchCode !== '') {
                    $rolloutAudit['batchCodes'][$batchCode] = true;
                }
                $stage = trim((string) ($rolloutInfo['stage'] ?? ''));
                if ($stage !== '') {
                    $rolloutAudit['byStage'][$stage] = (int) ($rolloutAudit['byStage'][$stage] ?? 0) + 1;
                }
            }
            $timeline[] = $this->buildTimelineEntry($row);
        }

        return [
            'items' => $rows,
            'summary' => [
                'total' => count($rows),
                'succeeded' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'succeeded')),
                'failed' => count(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === 'failed')),
                'queued' => count(array_filter($rows, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['queued', 'running'], true))),
                'byStatus' => $byStatus,
                'byCategory' => $byCategory,
                'overlayPipeline' => $overlayPipeline,
                'rolloutAudit' => [
                    'dispatchCount' => $rolloutAudit['dispatchCount'],
                    'blockedEntryCount' => $rolloutAudit['blockedEntryCount'],
                    'windowScheduledCount' => $rolloutAudit['windowScheduledCount'],
                    'batchCodes' => array_values(array_keys($rolloutAudit['batchCodes'])),
                    'byStage' => $rolloutAudit['byStage'],
                ],
                'timeline' => $timeline,
                'filters' => [
                    'subscriberCode' => $subscriberCode,
                    'status' => $status,
                    'category' => $category,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ],
            ],
        ];
    }

    private function evaluateReleaseEntity(SystemUpdateRelease $release, ?string $targetSubscriberCode = null): array
    {
        $currentVersion = $this->resolveCurrentVersion($targetSubscriberCode);
        $appliedVersions = $this->resolveAppliedVersions($targetSubscriberCode);
        $satisfiedVersions = $this->resolveSatisfiedVersions($this->buildReleaseCatalog(), $appliedVersions);

        return $this->evaluateRelease([
            'version' => $release->getVersion(),
            'title' => $release->getTitle(),
            'category' => $release->getCategory(),
            'severity' => $release->getSeverity(),
            'description' => $release->getDescription(),
            'autoApplySaas' => $release->isAutoApplySaas(),
            'autoApplyOnPrem' => $release->isAutoApplyOnPrem(),
            'autoApply' => $release->isAutoApplySaas() && $release->isAutoApplyOnPrem(),
            'requiresSubscriberConsent' => $release->isRequiresSubscriberConsent(),
            'blocksNextUpdates' => $release->isBlocksNextUpdates(),
            'internetRequired' => $release->isInternetRequired(),
            'requiresVersionMin' => $release->getRequiresVersionMin(),
            'requiresAppliedUpdates' => $release->getRequiresAppliedUpdates(),
            'replaces' => $release->getReplaces(),
            'breakingLevel' => $release->getBreakingLevel(),
            'steps' => $release->getSteps(),
            'programUpdates' => $release->getProgramUpdates(),
            'metadata' => $release->getMetadata(),
            'manifestSource' => $release->getManifestSource(),
            'manifestHash' => $release->getManifestHash(),
            'publishedAt' => $release->getPublishedAt()?->format(DATE_ATOM),
        ], $currentVersion, $appliedVersions, $satisfiedVersions, $this->deploymentMode->resolve(), true, '', $targetSubscriberCode);
    }

    private function normalizeRelease(array $payload, string $source, string $hash): array
    {
        $publishedAt = trim((string) ($payload['publishedAt'] ?? ''));
        $autoApply = $this->normalizeAutoApply($payload['autoApply'] ?? null, $payload);
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $metadata['channels'] = $this->normalizeChannels($payload, $metadata);
        $metadata['changelog'] = $this->normalizeChangelog($metadata['changelog'] ?? []);
        return [
            'version' => trim((string) ($payload['version'] ?? '')),
            'title' => trim((string) ($payload['title'] ?? 'Atualizacao sem titulo')),
            'category' => $this->normalizeCategory((string) ($payload['category'] ?? 'recommended')),
            'severity' => $this->normalizeSeverity((string) ($payload['severity'] ?? 'medium')),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'autoApplySaas' => $autoApply['saas'],
            'autoApplyOnPrem' => $autoApply['onprem'],
            'autoApply' => $autoApply['saas'] === true && $autoApply['onprem'] === true,
            'requiresSubscriberConsent' => ($payload['requiresSubscriberConsent'] ?? true) !== false,
            'blocksNextUpdates' => ($payload['blocksNextUpdates'] ?? false) === true,
            'internetRequired' => ($payload['internetRequired'] ?? false) === true,
            'requiresVersionMin' => trim((string) ($payload['requiresVersionMin'] ?? '')) ?: null,
            'requiresAppliedUpdates' => array_values(array_filter(array_map('strval', (array) ($payload['requiresAppliedUpdates'] ?? [])))),
            'replaces' => array_values(array_filter(array_map('strval', (array) ($payload['replaces'] ?? [])))),
            'breakingLevel' => $this->normalizeBreakingLevel((string) ($payload['breakingLevel'] ?? 'non_breaking')),
            'steps' => SystemUpdateStepCatalog::normalizeList((array) ($payload['steps'] ?? [])),
            'programUpdates' => array_values(array_filter((array) ($payload['programUpdates'] ?? []), 'is_array')),
            'metadata' => $metadata,
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
            ->setReplaces((array) ($data['replaces'] ?? []))
            ->setBreakingLevel((string) ($data['breakingLevel'] ?? 'non_breaking'))
            ->setSteps((array) ($data['steps'] ?? []))
            ->setProgramUpdates((array) ($data['programUpdates'] ?? []))
            ->setMetadata((array) ($data['metadata'] ?? []))
            ->setManifestSource($data['manifestSource'] ?? null)
            ->setManifestHash($data['manifestHash'] ?? null)
            ->setCheckedAt(new \DateTimeImmutable());

        $publishedAt = $data['publishedAt'] ?? null;
        $release->setPublishedAt($publishedAt ? new \DateTimeImmutable((string) $publishedAt) : null);
    }

    private function resolveCurrentVersion(?string $targetSubscriberCode = null): string
    {
        return $this->executions->findLatestSuccessfulVersionBySubscriber($targetSubscriberCode) ?? $this->systemVersion->resolve();
    }

    /**
     * @return list<string>
     */
    private function resolveAppliedVersions(?string $targetSubscriberCode = null): array
    {
        return $this->executions->findSuccessfulVersionsBySubscriber($targetSubscriberCode, 200);
    }

    private function evaluateRelease(array $release, string $currentVersion, array $appliedVersions, array $satisfiedVersions, string $deploymentMode, bool $manifestTrusted, string $manifestMessage, ?string $targetSubscriberCode = null): array
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
            if (!in_array((string) $requiredVersion, $satisfiedVersions, true)) {
                $status = 'blocked_dependency';
                $dependencyIssues[] = 'Atualizacao obrigatoria pendente: ' . $requiredVersion . '.';
            }
        }
        if (in_array((string) ($release['version'] ?? ''), $appliedVersions, true)) {
            $status = 'applied';
        } elseif (in_array((string) ($release['version'] ?? ''), $satisfiedVersions, true)) {
            $status = 'superseded';
        }

        $requiresConsent = ($release['requiresSubscriberConsent'] ?? true) !== false;
        $autoApplicable = $status === 'pending' && $this->isAutoApplicable($release, $deploymentMode);
        $impactReport = $this->analyzeProgramCustomizationImpact((array) ($release['programUpdates'] ?? []), $targetSubscriberCode);
        $targetChannel = $this->resolveTargetChannel($targetSubscriberCode);
        $channels = $this->normalizeChannels($release, is_array($release['metadata'] ?? null) ? $release['metadata'] : []);
        if ($status === 'pending' && !in_array($targetChannel, $channels, true)) {
            $status = 'channel_unavailable';
            $dependencyIssues[] = 'A release esta publicada apenas para os canais ' . implode(', ', $channels) . '.';
            $autoApplicable = false;
        }
        if ($status === 'pending' && ($impactReport['blockingCustomizationCount'] ?? 0) > 0 && (($release['metadata']['blockOnCustomizationConflict'] ?? false) === true)) {
            $status = 'blocked_customization';
            $dependencyIssues[] = 'Existem customizacoes de assinante que exigem rebase antes desta release.';
        }
        $consent = $this->resolveConsent((string) ($release['version'] ?? ''), $targetSubscriberCode);
        $consentStatus = $requiresConsent ? ($consent?->getStatus() ?? 'pending') : 'not-required';
        $consentApproved = $requiresConsent ? ($consent && $consent->getStatus() === 'approved') : true;
        $scenarioBehavior = $this->buildScenarioBehavior($release, $deploymentMode);
        $deploymentRule = $this->buildSubscriberDeploymentRule($targetSubscriberCode, $release, $scenarioBehavior);
        $rolloutWindow = $deploymentMode === 'saas' ? $this->resolveSaasRolloutWindow((array) ($release['metadata'] ?? [])) : null;
        $rolloutWindowStatus = is_array($rolloutWindow) ? (string) ($rolloutWindow['status'] ?? 'unscheduled') : 'not-applicable';
        $autoQueueAllowed = $autoApplicable;
        if ($autoQueueAllowed && $deploymentMode === 'saas' && is_array($rolloutWindow) && ($rolloutWindow['requiresWindow'] ?? false) === true) {
            $autoQueueAllowed = $rolloutWindowStatus === 'open';
            if (!$autoQueueAllowed) {
                $dependencyIssues[] = 'A release automatica aguarda a janela SaaS configurada para rollout.';
            }
        }
        $tenantActivationRequired = $deploymentMode === 'saas'
            && trim((string) $targetSubscriberCode) !== ''
            && (string) ($scenarioBehavior['applyMode'] ?? '') === 'tenant_activation';
        $tenantActivation = $tenantActivationRequired ? $this->resolveActivation((string) ($release['version'] ?? ''), (string) $targetSubscriberCode) : null;
        $tenantActivationStatus = $tenantActivationRequired ? ($tenantActivation?->getStatus() ?? 'pending') : 'not-required';
        if ($status === 'pending' && $tenantActivationRequired && ($deploymentRule['supportsPerTenantActivation'] ?? true) !== true) {
            $tenantActivationStatus = $this->resolveSharedRuntimeActivationStatus((string) ($release['version'] ?? ''), $deploymentRule);
            if ($tenantActivationStatus !== 'enabled') {
                $status = 'awaiting_runtime_activation';
                $dependencyIssues[] = 'A release opcional exige ativacao do runtime compartilhado antes do apply.';
                $autoApplicable = false;
            }
        }
        if ($status === 'pending' && $tenantActivationRequired && $tenantActivationStatus !== 'enabled') {
            $status = 'awaiting_tenant_activation';
            $dependencyIssues[] = 'A release opcional exige ativacao explicita para este assinante.';
            $autoApplicable = false;
        }
        $packageUrl = trim((string) ($release['metadata']['packageUrl'] ?? ''));
        $precheck = $this->buildCompatibilityPrecheck($release, [
            'status' => $status,
            'consentApproved' => $consentApproved,
            'consentStatus' => $consentStatus,
            'tenantActivationRequired' => $tenantActivationRequired,
            'tenantActivationStatus' => $tenantActivationStatus,
            'impactReport' => $impactReport,
            'scenarioBehavior' => $scenarioBehavior,
            'rolloutWindow' => $rolloutWindow,
            'rolloutWindowStatus' => $rolloutWindowStatus,
            'dependencyIssues' => $dependencyIssues,
            'autoApplicable' => $autoApplicable,
        ], $targetSubscriberCode);

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
            'tenantActivationRequired' => $tenantActivationRequired,
            'tenantActivationStatus' => $tenantActivationStatus,
            'tenantActivationInfo' => $tenantActivation ? $this->formatActivation($tenantActivation) : null,
            'autoApplicable' => $autoApplicable,
            'autoQueueAllowed' => $autoQueueAllowed,
            'breakingLevel' => (string) ($release['breakingLevel'] ?? 'non_breaking'),
            'scenarioBehavior' => $scenarioBehavior,
            'deploymentRule' => $deploymentRule,
            'channels' => $channels,
            'targetChannel' => $targetChannel,
            'channelStatus' => in_array($targetChannel, $channels, true) ? 'eligible' : 'out_of_channel',
            'rolloutWindow' => $rolloutWindow,
            'rolloutWindowStatus' => $rolloutWindowStatus,
            'blocksNextUpdates' => ($release['blocksNextUpdates'] ?? false) === true,
            'dependencyIssues' => $dependencyIssues,
            'requiresVersionMin' => $requiresVersionMin !== '' ? $requiresVersionMin : null,
            'requiresAppliedUpdates' => array_values((array) ($release['requiresAppliedUpdates'] ?? [])),
            'replaces' => array_values((array) ($release['replaces'] ?? [])),
            'steps' => array_values((array) ($release['steps'] ?? [])),
            'stepCatalog' => array_values((array) ($release['steps'] ?? [])),
            'changelog' => $this->normalizeChangelog($release['metadata']['changelog'] ?? []),
            'manifestSource' => $release['manifestSource'] ?? null,
            'metadata' => is_array($release['metadata'] ?? null) ? $release['metadata'] : [],
            'impactReport' => $impactReport,
            'compatibilityPrecheck' => $precheck,
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
            if (($item['status'] ?? '') !== 'pending' || ($item['autoQueueAllowed'] ?? false) !== true) {
                continue;
            }
            if ($deploymentMode === 'onprem' && $this->resolveOnPremCriticalMode() === 'download_only') {
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

    private function resolveOnPremCriticalMode(): string
    {
        $value = strtolower(trim((string) ($_SERVER['APP_UPDATE_ONPREM_CRITICAL_MODE'] ?? $_ENV['APP_UPDATE_ONPREM_CRITICAL_MODE'] ?? getenv('APP_UPDATE_ONPREM_CRITICAL_MODE') ?: '')));
        if (in_array($value, ['auto', 'prompt_admin', 'download_only'], true)) {
            return $value;
        }

        $legacy = strtolower(trim((string) ($_SERVER['APP_UPDATE_ONPREM_CRITICAL_POLICY'] ?? $_ENV['APP_UPDATE_ONPREM_CRITICAL_POLICY'] ?? getenv('APP_UPDATE_ONPREM_CRITICAL_POLICY') ?: '')));
        if (in_array($legacy, ['auto', 'prompt_admin', 'download_only'], true)) {
            return $legacy;
        }

        return 'prompt_admin';
    }

    private function resolveOnPremCriticalAccessPolicy(): string
    {
        $value = strtolower(trim((string) ($_SERVER['APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY'] ?? $_ENV['APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY'] ?? getenv('APP_UPDATE_ONPREM_CRITICAL_ACCESS_POLICY') ?: '')));
        if (in_array($value, ['warn', 'block'], true)) {
            return $value;
        }

        $legacy = strtolower(trim((string) ($_SERVER['APP_UPDATE_ONPREM_CRITICAL_POLICY'] ?? $_ENV['APP_UPDATE_ONPREM_CRITICAL_POLICY'] ?? getenv('APP_UPDATE_ONPREM_CRITICAL_POLICY') ?: '')));
        if (in_array($legacy, ['warn', 'block'], true)) {
            return $legacy;
        }

        return 'warn';
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

    private function normalizeActivationStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, ['enabled', 'disabled'], true) ? $normalized : 'enabled';
    }

    private function releaseToArray(SystemUpdateRelease $release): array
    {
        return [
            'version' => $release->getVersion(),
            'title' => $release->getTitle(),
            'category' => $release->getCategory(),
            'severity' => $release->getSeverity(),
            'description' => $release->getDescription(),
            'autoApply' => $release->isAutoApplySaas() && $release->isAutoApplyOnPrem(),
            'autoApplySaas' => $release->isAutoApplySaas(),
            'autoApplyOnPrem' => $release->isAutoApplyOnPrem(),
            'requiresSubscriberConsent' => $release->isRequiresSubscriberConsent(),
            'blocksNextUpdates' => $release->isBlocksNextUpdates(),
            'internetRequired' => $release->isInternetRequired(),
            'requiresVersionMin' => $release->getRequiresVersionMin(),
            'requiresAppliedUpdates' => $release->getRequiresAppliedUpdates(),
            'replaces' => $release->getReplaces(),
            'breakingLevel' => $release->getBreakingLevel(),
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

    /**
     * @param array<int, array<string, mixed>> $releaseCatalog
     * @param list<string> $appliedVersions
     * @return list<string>
     */
    private function resolveSatisfiedVersions(array $releaseCatalog, array $appliedVersions): array
    {
        $covered = [];
        foreach ($appliedVersions as $version) {
            $normalizedVersion = trim((string) $version);
            if ($normalizedVersion === '') {
                continue;
            }
            $covered[$normalizedVersion] = true;
            foreach ($releaseCatalog as $release) {
                if ((string) ($release['version'] ?? '') !== $normalizedVersion) {
                    continue;
                }
                foreach ((array) ($release['replaces'] ?? []) as $replacedVersion) {
                    $replacedVersion = trim((string) $replacedVersion);
                    if ($replacedVersion !== '') {
                        $covered[$replacedVersion] = true;
                    }
                }
            }
        }

        return array_keys($covered);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildReleaseCatalog(): array
    {
        return array_map(fn (SystemUpdateRelease $release): array => $this->releaseToArray($release), $this->releases->findAllOrdered());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function normalizeChannels(array $payload, array $metadata): array
    {
        $channels = $payload['channels'] ?? $metadata['channels'] ?? $metadata['channel'] ?? ['stable'];
        $normalized = array_values(array_filter(array_map(static function ($value): string {
            return strtolower(trim((string) $value));
        }, is_array($channels) ? $channels : [$channels]), static fn (string $value): bool => $value !== ''));

        return $normalized ?: ['stable'];
    }

    /**
     * @param mixed $changelog
     * @return list<array<string, mixed>>
     */
    private function normalizeChangelog(mixed $changelog): array
    {
        if (!is_array($changelog)) {
            return [];
        }

        $sections = [];
        foreach ($changelog as $section) {
            if (!is_array($section)) {
                continue;
            }
            $title = trim((string) ($section['title'] ?? ''));
            $items = array_values(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, (array) ($section['items'] ?? [])), static fn (string $value): bool => $value !== ''));
            $sections[] = [
                'title' => $title !== '' ? $title : 'Sem titulo',
                'items' => $items,
                'impact' => trim((string) ($section['impact'] ?? '')) ?: null,
                'risk' => trim((string) ($section['risk'] ?? '')) ?: null,
                'reversible' => ($section['reversible'] ?? true) !== false,
                'actionRequired' => trim((string) ($section['actionRequired'] ?? '')) ?: null,
            ];
        }

        return $sections;
    }

    /**
     * @param mixed $autoApply
     * @param array<string, mixed> $payload
     * @return array{saas: bool, onprem: bool}
     */
    private function normalizeAutoApply(mixed $autoApply, array $payload): array
    {
        $saas = ($payload['autoApplySaas'] ?? null) === true;
        $onprem = ($payload['autoApplyOnPrem'] ?? null) === true;

        if (array_key_exists('autoApplySaas', $payload) || array_key_exists('autoApplyOnPrem', $payload)) {
            return [
                'saas' => $saas,
                'onprem' => $onprem,
            ];
        }

        if (is_bool($autoApply)) {
            return [
                'saas' => $autoApply,
                'onprem' => $autoApply,
            ];
        }

        if (is_array($autoApply)) {
            return [
                'saas' => ($autoApply['saas'] ?? false) === true,
                'onprem' => ($autoApply['onprem'] ?? false) === true,
            ];
        }

        return [
            'saas' => false,
            'onprem' => false,
        ];
    }

    private function normalizeBreakingLevel(string $breakingLevel): string
    {
        $normalized = strtolower(trim($breakingLevel));
        return $normalized !== '' ? mb_substr($normalized, 0, 30) : 'non_breaking';
    }

    /**
     * @param array<string, mixed> $release
     * @param array<string, mixed> $evaluation
     * @return array<string, mixed>
     */
    private function buildCompatibilityPrecheck(array $release, array $evaluation, ?string $targetSubscriberCode = null): array
    {
        $checks = [];
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];
        $packageConfigured = trim((string) ($metadata['packageUrl'] ?? '')) !== '';
        $packageHashConfigured = trim((string) ($metadata['packageHash'] ?? '')) !== '';
        $rolloutWindowStatus = (string) ($evaluation['rolloutWindowStatus'] ?? 'unscheduled');
        $impactReport = is_array($evaluation['impactReport'] ?? null) ? $evaluation['impactReport'] : [];
        $overlaySummary = is_array($impactReport['overlayPipelineSummary'] ?? null) ? $impactReport['overlayPipelineSummary'] : [];
        $requiresBackup = ($metadata['requiresBackup'] ?? false) === true;
        $requiresMaintenance = ($metadata['requiresMaintenanceMode'] ?? false) === true;
        $deploymentMode = $this->deploymentMode->resolve();
        $orchestratorExpected = $deploymentMode === 'saas' && (($metadata['orchestratorDispatchEnabled'] ?? true) === true);

        $checks[] = $this->precheckItem('manifest', 'Manifesto confiavel', 'ok', 'Manifesto validado para a release.');
        $checks[] = $this->precheckItem(
            'version_chain',
            'Cadeia de versoes',
            in_array((string) ($evaluation['status'] ?? ''), ['blocked_dependency'], true) ? 'blocked' : 'ok',
            in_array((string) ($evaluation['status'] ?? ''), ['blocked_dependency'], true)
                ? implode(' ', (array) ($evaluation['dependencyIssues'] ?? []))
                : 'Dependencias de versao satisfeitas.'
        );
        $checks[] = $this->precheckItem(
            'channel',
            'Canal do assinante',
            (string) ($evaluation['channelStatus'] ?? 'eligible') === 'eligible' ? 'ok' : 'blocked',
            (string) ($evaluation['channelStatus'] ?? 'eligible') === 'eligible'
                ? 'Release disponivel para o canal ' . (string) ($evaluation['targetChannel'] ?? 'stable') . '.'
                : 'Release fora do canal do assinante.'
        );
        $checks[] = $this->precheckItem(
            'consent',
            'Anuencia',
            ($evaluation['requiresConsent'] ?? false) !== true || ($evaluation['consentApproved'] ?? false) === true ? 'ok' : 'warning',
            ($evaluation['requiresConsent'] ?? false) !== true
                ? 'Release nao exige anuencia.'
                : (($evaluation['consentApproved'] ?? false) === true ? 'Anuencia registrada.' : 'A release exige anuencia antes da aplicacao.')
        );
        $checks[] = $this->precheckItem(
            'tenant_activation',
            'Ativacao por tenant',
            ($evaluation['tenantActivationRequired'] ?? false) !== true || ($evaluation['tenantActivationStatus'] ?? '') === 'enabled' ? 'ok' : 'warning',
            ($evaluation['tenantActivationRequired'] ?? false) !== true
                ? 'Sem ativacao especifica por tenant.'
                : (($evaluation['tenantActivationStatus'] ?? '') === 'enabled' ? 'Tenant ativado para a release.' : 'Aguardando ativacao explicita do tenant.')
        );
        $checks[] = $this->precheckItem(
            'package',
            'Pacote da release',
            $packageConfigured && $packageHashConfigured ? 'ok' : 'blocked',
            $packageConfigured && $packageHashConfigured
                ? 'Pacote e hash configurados no manifesto.'
                : 'Pacote ou hash ausente para esta release.'
        );
        $checks[] = $this->precheckItem(
            'backup',
            'Backup',
            $requiresBackup ? 'warning' : 'ok',
            $requiresBackup ? 'A release exige backup antes da aplicacao.' : 'Sem exigencia de backup.'
        );
        $checks[] = $this->precheckItem(
            'maintenance',
            'Janela de manutencao',
            $requiresMaintenance ? 'warning' : 'ok',
            $requiresMaintenance ? 'A release exige janela de manutencao.' : 'A release pode seguir sem manutencao dedicada.'
        );
        $checks[] = $this->precheckItem(
            'rollout_window',
            'Janela de rollout',
            $deploymentMode !== 'saas' || $rolloutWindowStatus === 'open' || $rolloutWindowStatus === 'unscheduled' ? 'ok' : 'warning',
            $deploymentMode !== 'saas'
                ? 'Nao se aplica ao on-premise.'
                : ($rolloutWindowStatus === 'open' || $rolloutWindowStatus === 'unscheduled'
                    ? 'Janela de rollout pronta para uso.'
                    : 'A release aguarda a abertura da janela SaaS.')
        );
        $checks[] = $this->precheckItem(
            'customization',
            'Customizacoes',
            ($impactReport['blockingCustomizationCount'] ?? 0) > 0 ? 'blocked' : ((int) ($overlaySummary['reviewRequired'] ?? 0) > 0 ? 'warning' : 'ok'),
            ($impactReport['blockingCustomizationCount'] ?? 0) > 0
                ? 'Existe customizacao bloqueante antes da aplicacao.'
                : ((int) ($overlaySummary['reviewRequired'] ?? 0) > 0
                    ? 'Ha overlays que exigem revisao manual.'
                    : 'Sem bloqueios de customizacao para esta release.')
        );
        $checks[] = $this->precheckItem(
            'orchestrator',
            'Orquestrador',
            $deploymentMode !== 'saas' || !$orchestratorExpected || $this->orchestrator->isEnabled() ? 'ok' : 'warning',
            $deploymentMode !== 'saas'
                ? 'Nao se aplica ao on-premise.'
                : ($this->orchestrator->isEnabled() ? 'Orquestrador habilitado para rollout.' : 'Rollout SaaS sem orquestrador ativo.')
        );

        $blockingCount = count(array_filter($checks, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'blocked'));
        $warningCount = count(array_filter($checks, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'warning'));

        return [
            'targetSubscriberCode' => $targetSubscriberCode,
            'checks' => $checks,
            'blockingCount' => $blockingCount,
            'warningCount' => $warningCount,
            'status' => $blockingCount > 0 ? 'blocked' : ($warningCount > 0 ? 'warning' : 'ok'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function precheckItem(string $code, string $title, string $status, string $message): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $releasePayload
     * @return array<string, mixed>
     */
    private function buildSubscriberImpactReport(array $releasePayload, ?string $targetSubscriberCode = null, ?string $batchCode = null): array
    {
        $subscribers = $targetSubscriberCode
            ? array_values(array_filter($this->listTargetSubscribers(), static fn (array $item): bool => (string) ($item['code'] ?? '') === trim((string) $targetSubscriberCode)))
            : $this->listTargetSubscribers();
        $batchFilter = trim((string) $batchCode);
        if ($batchFilter !== '' && $subscribers) {
            $releaseEntity = $this->releases->findOneByVersion((string) ($releasePayload['version'] ?? ''));
            if ($releaseEntity) {
                $allowedCodes = [];
                foreach ($this->resolveSaasRolloutBatches($releaseEntity, null) as $batch) {
                    if ((string) ($batch['code'] ?? '') !== $batchFilter) {
                        continue;
                    }
                    foreach ((array) ($batch['subscribers'] ?? []) as $subscriber) {
                        $allowedCodes[] = (string) ($subscriber['code'] ?? '');
                    }
                }
                $subscribers = array_values(array_filter($subscribers, static fn (array $item): bool => in_array((string) ($item['code'] ?? ''), $allowedCodes, true)));
            }
        }

        $items = [];
        $summary = [
            'totalSubscribers' => count($subscribers),
            'autoApplicable' => 0,
            'requiresConsent' => 0,
            'awaitingActivation' => 0,
            'blockedDependency' => 0,
            'blockedCustomization' => 0,
            'channelUnavailable' => 0,
            'ready' => 0,
        ];

        foreach ($subscribers as $subscriber) {
            $code = (string) ($subscriber['code'] ?? '');
            $evaluation = $this->evaluateRelease(
                $releasePayload,
                $this->resolveCurrentVersion($code),
                $this->resolveAppliedVersions($code),
                $this->resolveSatisfiedVersions($this->buildReleaseCatalog(), $this->resolveAppliedVersions($code)),
                $this->deploymentMode->resolve(),
                true,
                '',
                $code
            );
            $precheck = $this->buildCompatibilityPrecheck($releasePayload, $evaluation, $code);
            $items[] = [
                'subscriber' => $subscriber,
                'status' => $evaluation['status'] ?? 'unknown',
                'autoApplicable' => ($evaluation['autoApplicable'] ?? false) === true,
                'requiresConsent' => ($evaluation['requiresConsent'] ?? false) === true,
                'consentStatus' => $evaluation['consentStatus'] ?? 'not-required',
                'tenantActivationStatus' => $evaluation['tenantActivationStatus'] ?? 'not-required',
                'channel' => $evaluation['targetChannel'] ?? 'stable',
                'compatibilityPrecheck' => $precheck,
                'blockingCustomizationCount' => (int) (($evaluation['impactReport']['blockingCustomizationCount'] ?? 0)),
                'dependencyIssues' => $evaluation['dependencyIssues'] ?? [],
            ];

            if (($evaluation['autoApplicable'] ?? false) === true) {
                $summary['autoApplicable']++;
            }
            if (($evaluation['requiresConsent'] ?? false) === true && ($evaluation['consentApproved'] ?? false) !== true) {
                $summary['requiresConsent']++;
            }
            if (($evaluation['tenantActivationRequired'] ?? false) === true && ($evaluation['tenantActivationStatus'] ?? '') !== 'enabled') {
                $summary['awaitingActivation']++;
            }
            if (($evaluation['status'] ?? '') === 'blocked_dependency') {
                $summary['blockedDependency']++;
            }
            if (($evaluation['status'] ?? '') === 'blocked_customization') {
                $summary['blockedCustomization']++;
            }
            if (($evaluation['status'] ?? '') === 'channel_unavailable') {
                $summary['channelUnavailable']++;
            }
            if (($evaluation['status'] ?? '') === 'pending' && ($precheck['blockingCount'] ?? 0) === 0) {
                $summary['ready']++;
            }
        }

        return [
            'items' => $items,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $releaseCatalog
     * @return array<string, int>
     */
    private function buildDelayDashboard(array $releaseCatalog, ?string $targetSubscriberCode = null): array
    {
        $summary = [
            'blockedDependency' => 0,
            'requiresConsent' => 0,
            'awaitingActivation' => 0,
            'blockedCustomization' => 0,
            'channelUnavailable' => 0,
            'ready' => 0,
        ];
        foreach ($releaseCatalog as $releasePayload) {
            if (!is_array($releasePayload) || trim((string) ($releasePayload['version'] ?? '')) === '') {
                continue;
            }
            $report = $this->buildSubscriberImpactReport($releasePayload, $targetSubscriberCode);
            $itemSummary = (array) ($report['summary'] ?? []);
            $summary['blockedDependency'] += (int) ($itemSummary['blockedDependency'] ?? 0);
            $summary['requiresConsent'] += (int) ($itemSummary['requiresConsent'] ?? 0);
            $summary['awaitingActivation'] += (int) ($itemSummary['awaitingActivation'] ?? 0);
            $summary['blockedCustomization'] += (int) ($itemSummary['blockedCustomization'] ?? 0);
            $summary['channelUnavailable'] += (int) ($itemSummary['channelUnavailable'] ?? 0);
            $summary['ready'] += (int) ($itemSummary['ready'] ?? 0);
        }
        $failedRollout = 0;
        foreach ($this->listExecutionHistory([
            'subscriberCode' => $targetSubscriberCode,
            'limit' => 120,
        ])['items'] ?? [] as $item) {
            if ((string) ($item['mode'] ?? '') === 'rollout_dispatch' && (string) ($item['status'] ?? '') === 'failed') {
                $failedRollout++;
            }
        }

        return [
            'outdatedSubscribers' => (int) (($summary['blockedDependency'] ?? 0) + ($summary['ready'] ?? 0) + ($summary['requiresConsent'] ?? 0) + ($summary['awaitingActivation'] ?? 0)),
            'blockedDependencySubscribers' => (int) ($summary['blockedDependency'] ?? 0),
            'awaitingConsentSubscribers' => (int) ($summary['requiresConsent'] ?? 0),
            'awaitingActivationSubscribers' => (int) ($summary['awaitingActivation'] ?? 0),
            'blockedCustomizationSubscribers' => (int) ($summary['blockedCustomization'] ?? 0),
            'channelUnavailableSubscribers' => (int) ($summary['channelUnavailable'] ?? 0),
            'failedRolloutSubscribers' => $failedRollout,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, int> $delayDashboard
     * @return list<array<string, mixed>>
     */
    private function buildOperationalAlerts(array $items, array $delayDashboard, ?string $targetSubscriberCode = null): array
    {
        $alerts = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'blocked_dependency') {
                $alerts[] = [
                    'severity' => 'high',
                    'kind' => 'dependency',
                    'releaseVersion' => $item['version'] ?? null,
                    'subscriberCode' => $targetSubscriberCode,
                    'message' => 'A release ' . (string) ($item['version'] ?? '') . ' esta bloqueada pela cadeia obrigatoria.',
                ];
            }
            if ($status === 'awaiting_tenant_activation') {
                $alerts[] = [
                    'severity' => 'medium',
                    'kind' => 'tenant_activation',
                    'releaseVersion' => $item['version'] ?? null,
                    'subscriberCode' => $targetSubscriberCode,
                    'message' => 'A release opcional ' . (string) ($item['version'] ?? '') . ' aguarda ativacao do tenant.',
                ];
            }
            if ($status === 'blocked_customization') {
                $alerts[] = [
                    'severity' => 'high',
                    'kind' => 'customization',
                    'releaseVersion' => $item['version'] ?? null,
                    'subscriberCode' => $targetSubscriberCode,
                    'message' => 'A release ' . (string) ($item['version'] ?? '') . ' possui bloqueio por customizacao.',
                ];
            }
        }
        if (($delayDashboard['failedRolloutSubscribers'] ?? 0) > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'kind' => 'rollout_failed',
                'releaseVersion' => null,
                'subscriberCode' => $targetSubscriberCode,
                'message' => 'Existem assinantes com falha de rollout em execucoes recentes.',
            ];
        }

        return $alerts;
    }

    private function resolveTargetChannel(?string $targetSubscriberCode): string
    {
        $targetCode = trim((string) $targetSubscriberCode);
        if ($targetCode === '') {
            return 'stable';
        }
        $subscriber = $this->subscribers->findEnabledByCode($targetCode) ?? $this->subscribers->findOneBy(['code' => $targetCode]);
        if (!$subscriber) {
            return 'stable';
        }
        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];

        return strtolower(trim((string) ($provisioning['updateChannel'] ?? 'stable'))) ?: 'stable';
    }

    private function buildRollbackPlan(SystemUpdateRelease $release, ?string $targetSubscriberCode = null, ?string $targetVersion = null): array
    {
        $metadata = $release->getMetadata();
        $steps = [];
        if (is_array($metadata['rollbackSteps'] ?? null) && $metadata['rollbackSteps']) {
            $steps = SystemUpdateStepCatalog::normalizeList((array) $metadata['rollbackSteps']);
        } else {
            foreach (SystemUpdateStepCatalog::normalizeList($release->getSteps()) as $step) {
                $rollbackStep = trim((string) ($step['rollbackStep'] ?? ''));
                if ($rollbackStep !== '') {
                    $steps[] = SystemUpdateStepCatalog::normalize(['code' => $rollbackStep]);
                }
            }
            $steps = array_values(array_filter($steps));
        }
        $resolvedTargetVersion = trim((string) $targetVersion);
        if ($resolvedTargetVersion === '') {
            $resolvedTargetVersion = trim((string) ($metadata['rollbackTargetVersion'] ?? ''));
        }
        if ($resolvedTargetVersion === '' && $release->getReplaces()) {
            $resolvedTargetVersion = (string) ($release->getReplaces()[0] ?? '');
        }
        if ($resolvedTargetVersion === '') {
            $resolvedTargetVersion = (string) ($this->executions->findLatestSuccessfulVersionBySubscriber($targetSubscriberCode) ?? '');
            if ($resolvedTargetVersion === $release->getVersion()) {
                $resolvedTargetVersion = '';
            }
        }

        $supported = $resolvedTargetVersion !== '' || count($steps) > 0 || $this->orchestrator->isEnabled();

        return [
            'supported' => $supported,
            'targetVersion' => $resolvedTargetVersion !== '' ? $resolvedTargetVersion : null,
            'steps' => $steps,
            'dispatchRollback' => $this->deploymentMode->resolve() === 'saas' && $this->orchestrator->isEnabled(),
            'requiresBackup' => ($metadata['requiresBackup'] ?? false) === true,
            'requiresMaintenanceMode' => ($metadata['requiresMaintenanceMode'] ?? false) === true,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildTimelineEntry(array $row): array
    {
        $mode = (string) ($row['mode'] ?? 'manual');
        $status = (string) ($row['status'] ?? 'queued');
        $title = match ($mode) {
            'rollback' => 'Rollback da release',
            'rollout_dispatch' => 'Despacho de rollout',
            'auto' => 'Aplicacao automatica',
            'consented' => 'Aplicacao com anuencia',
            default => 'Aplicacao da release',
        };

        return [
            'id' => $row['id'] ?? null,
            'releaseVersion' => $row['releaseVersion'] ?? null,
            'title' => $title,
            'status' => $status,
            'mode' => $mode,
            'createdAt' => $row['createdAt'] ?? null,
            'finishedAt' => $row['finishedAt'] ?? null,
            'subscriberCode' => $row['targetSubscriberCode'] ?? null,
            'message' => $row['summary']['message'] ?? null,
        ];
    }

    private function describeOnPremCriticalMode(?string $mode): string
    {
        return match ($mode) {
            'auto' => 'aplicacao automatica quando a release permitir',
            'download_only' => 'download local do pacote antes da decisao administrativa',
            default => 'acao administrativa local antes da continuidade',
        };
    }

    private function buildScenarioBehavior(array $release, string $deploymentMode): array
    {
        $category = (string) ($release['category'] ?? 'recommended');
        $metadata = is_array($release['metadata'] ?? null) ? $release['metadata'] : [];

        if ($deploymentMode === 'saas') {
            return match ($category) {
                'security_critical' => [
                    'control' => 'provider',
                    'applyMode' => 'automatic',
                    'rolloutMode' => (string) ($metadata['saasRolloutMode'] ?? 'short_window'),
                    'tenantActivationRequired' => false,
                    'entryBlockAllowed' => ($metadata['saasBlockEntryUntilComplete'] ?? false) === true,
                ],
                'required_structural' => [
                    'control' => 'provider',
                    'applyMode' => (($release['autoApplySaas'] ?? false) === true) ? 'automatic' : 'controlled',
                    'rolloutMode' => (string) ($metadata['saasRolloutMode'] ?? 'progressive_by_tenant'),
                    'tenantActivationRequired' => false,
                    'entryBlockAllowed' => false,
                ],
                default => [
                    'control' => 'provider',
                    'applyMode' => 'tenant_activation',
                    'rolloutMode' => 'opt_in',
                    'tenantActivationRequired' => true,
                    'entryBlockAllowed' => false,
                ],
            };
        }

        if ($deploymentMode === 'onprem') {
            return match ($category) {
                'security_critical' => [
                    'control' => 'customer_governed',
                    'applyMode' => $this->resolveOnPremCriticalMode(),
                    'accessPolicy' => $this->resolveOnPremCriticalAccessPolicy(),
                ],
                'required_structural' => [
                    'control' => 'customer_governed',
                    'applyMode' => 'consent_or_manual',
                    'accessPolicy' => 'blocks_next_updates',
                ],
                default => [
                    'control' => 'customer_governed',
                    'applyMode' => 'manual_optional',
                    'accessPolicy' => 'panel_available',
                ],
            };
        }

        return [
            'control' => 'shared',
            'applyMode' => 'manual',
            'accessPolicy' => 'warning',
        ];
    }

    private function resolveConsent(string $version, ?string $targetSubscriberCode = null): ?SystemUpdateConsent
    {
        return $this->consents->findLatestByVersionAndSubscriber($version, $targetSubscriberCode);
    }

    private function resolveActivation(string $version, string $targetSubscriberCode): ?SystemUpdateTenantActivation
    {
        return $this->activations->findLatestByVersionAndSubscriber($version, $targetSubscriberCode);
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

    private function formatActivation(SystemUpdateTenantActivation $activation): array
    {
        return [
            'id' => $activation->getId(),
            'releaseVersion' => $activation->getReleaseVersion(),
            'status' => $activation->getStatus(),
            'decidedBy' => $activation->getDecidedBy(),
            'source' => $activation->getSource(),
            'deploymentMode' => $activation->getDeploymentMode(),
            'databaseIdentity' => $activation->getDatabaseIdentity(),
            'targetSubscriberCode' => $activation->getTargetSubscriberCode(),
            'targetSubscriberName' => $activation->getTargetSubscriberName(),
            'reason' => $activation->getReason(),
            'createdAt' => $activation->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function dispatchRolloutForSubscriber(SystemUpdateRelease $release, array $evaluation, AuthSubscriber $targetSubscriber, ?array $package, array $plan, ?array $batch = null): array
    {
        $payload = $this->buildOrchestratorPayload($release, $evaluation, $targetSubscriber, $package, null, $plan, $batch);
        $dispatch = $this->orchestrator->dispatch($payload);

        $execution = $this->newExecution($release, 'rollout_dispatch', 'ui', (array) ($evaluation['impactReport'] ?? []), $targetSubscriber)
            ->setStatus(($dispatch['status'] ?? '') === 'dispatched' ? 'succeeded' : 'failed')
            ->setSummary([
                'message' => ($dispatch['status'] ?? '') === 'dispatched'
                    ? 'Rollout SaaS despachado para o orquestrador.'
                    : 'Falha ao despachar o rollout SaaS.',
                'rolloutAudit' => [
                    'stage' => 'dispatch',
                    'dispatchCount' => 1,
                    'entryAccessMode' => $payload['entryAccessMode'] ?? 'warning',
                    'windowStatus' => $payload['rolloutWindow']['status'] ?? 'unscheduled',
                    'batchCode' => $payload['rolloutBatch']['code'] ?? null,
                    'batchTitle' => $payload['rolloutBatch']['title'] ?? null,
                    'windowStartAt' => $payload['rolloutWindow']['startAt'] ?? null,
                    'windowEndsAt' => $payload['rolloutWindow']['endAt'] ?? null,
                ],
                'orchestratorDispatch' => $dispatch,
            ])
            ->setFinishedAt(new \DateTimeImmutable());
        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        return [
            'targetSubscriber' => $this->formatTargetSubscriber($targetSubscriber),
            'batch' => $batch,
            'dispatch' => $dispatch,
            'payload' => $payload,
            'execution' => $this->formatExecution($execution),
        ];
    }

    private function resolveSaasRolloutWindow(array $metadata): array
    {
        $window = is_array($metadata['saasRolloutWindow'] ?? null) ? $metadata['saasRolloutWindow'] : [];
        $startAt = trim((string) ($window['startAt'] ?? ''));
        $durationMinutes = max(0, (int) ($window['durationMinutes'] ?? 0));
        $requiresWindow = $startAt !== '' || $durationMinutes > 0;
        $endAt = null;
        $status = 'unscheduled';

        if ($startAt !== '' && $durationMinutes > 0) {
            try {
                $start = new \DateTimeImmutable($startAt);
                $end = $start->modify('+' . $durationMinutes . ' minutes');
                $now = new \DateTimeImmutable();
                $endAt = $end->format(DATE_ATOM);
                if ($now < $start) {
                    $status = 'scheduled';
                } elseif ($now >= $start && $now <= $end) {
                    $status = 'open';
                } else {
                    $status = 'closed';
                }
            } catch (\Throwable) {
                $status = 'invalid';
            }
        }

        return [
            'requiresWindow' => $requiresWindow,
            'startAt' => $startAt !== '' ? $startAt : null,
            'durationMinutes' => $durationMinutes > 0 ? $durationMinutes : null,
            'endAt' => $endAt,
            'freezeNewSessions' => (($window['freezeNewSessions'] ?? false) === true) || (($metadata['saasBlockEntryUntilComplete'] ?? false) === true),
            'status' => $status,
        ];
    }

    private function buildSaasEntryBlockPlan(SystemUpdateRelease $release, array $rolloutWindow): array
    {
        $metadata = $release->getMetadata();
        $blockEntry = (($metadata['saasBlockEntryUntilComplete'] ?? false) === true) || (($rolloutWindow['freezeNewSessions'] ?? false) === true);

        return [
            'enabled' => $blockEntry,
            'accessMode' => $blockEntry ? 'blocked' : 'warning',
            'message' => $blockEntry
                ? 'A entrada do assinante deve ficar temporariamente bloqueada durante o rollout critico.'
                : 'A atualizacao SaaS pode seguir sem bloquear novas sessoes.',
        ];
    }

    private function resolveSaasRolloutBatches(SystemUpdateRelease $release, ?string $targetSubscriberCode = null): array
    {
        $metadata = $release->getMetadata();
        $targetCode = trim((string) $targetSubscriberCode);
        $availableSubscribers = $targetCode !== ''
            ? array_values(array_filter($this->listTargetSubscribers(), static fn (array $item): bool => (string) ($item['code'] ?? '') === $targetCode))
            : $this->listTargetSubscribers();
        if (!$availableSubscribers) {
            return [];
        }

        $configured = is_array($metadata['saasRolloutBatches'] ?? null) ? $metadata['saasRolloutBatches'] : [];
        $batches = [];
        if ($configured) {
            foreach ($configured as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $code = trim((string) ($item['code'] ?? ''));
                if ($code === '') {
                    $code = 'batch-' . ($index + 1);
                }
                $subscriberCodes = array_values(array_filter(array_map(static function ($value): string {
                    return trim((string) $value);
                }, (array) ($item['subscriberCodes'] ?? [])), static fn (string $value): bool => $value !== ''));
                $subscribers = array_values(array_filter($availableSubscribers, static function (array $subscriber) use ($subscriberCodes): bool {
                    return in_array((string) ($subscriber['code'] ?? ''), $subscriberCodes, true);
                }));
                if (!$subscribers) {
                    continue;
                }
                $batches[] = $this->formatRolloutBatch($release->getVersion(), [
                    'code' => $code,
                    'title' => trim((string) ($item['title'] ?? '')) ?: strtoupper($code),
                    'kind' => trim((string) ($item['kind'] ?? 'wave')) ?: 'wave',
                    'subscribers' => $subscribers,
                ]);
            }
        }

        if (!$batches) {
            $canaryCount = count($availableSubscribers) > 1 ? 1 : count($availableSubscribers);
            $canary = array_slice($availableSubscribers, 0, $canaryCount);
            $remaining = array_slice($availableSubscribers, $canaryCount);
            if ($canary) {
                $batches[] = $this->formatRolloutBatch($release->getVersion(), [
                    'code' => 'canary',
                    'title' => 'Canario inicial',
                    'kind' => 'canary',
                    'subscribers' => $canary,
                ]);
            }
            if ($remaining) {
                $batches[] = $this->formatRolloutBatch($release->getVersion(), [
                    'code' => 'wave-1',
                    'title' => 'Lote principal',
                    'kind' => 'wave',
                    'subscribers' => $remaining,
                ]);
            }
        }

        return $batches;
    }

    private function formatRolloutBatch(string $releaseVersion, array $batch): array
    {
        $subscribers = array_values((array) ($batch['subscribers'] ?? []));
        $subscriberCodes = array_values(array_filter(array_map(static function (array $subscriber): string {
            return trim((string) ($subscriber['code'] ?? ''));
        }, $subscribers), static fn (string $value): bool => $value !== ''));
        $executions = $this->executions->findByReleaseAndSubscribers($releaseVersion, $subscriberCodes, 200);

        $status = 'pending';
        if ($executions) {
            $latestStatuses = [];
            foreach ($executions as $execution) {
                $code = trim((string) $execution->getTargetSubscriberCode());
                if ($code === '' || array_key_exists($code, $latestStatuses)) {
                    continue;
                }
                $latestStatuses[$code] = $execution->getStatus();
            }
            $statuses = array_values($latestStatuses);
            if ($statuses && count(array_filter($statuses, static fn (string $item): bool => $item === 'succeeded')) === count($subscriberCodes)) {
                $status = 'completed';
            } elseif (count(array_filter($statuses, static fn (string $item): bool => in_array($item, ['queued', 'running'], true))) > 0) {
                $status = 'running';
            } elseif (count(array_filter($statuses, static fn (string $item): bool => $item === 'failed')) > 0) {
                $status = 'failed';
            } elseif (count(array_filter($statuses, static fn (string $item): bool => $item === 'succeeded')) > 0) {
                $status = 'partial';
            }
        }

        return [
            'code' => (string) ($batch['code'] ?? 'batch'),
            'title' => (string) ($batch['title'] ?? 'Lote'),
            'kind' => (string) ($batch['kind'] ?? 'wave'),
            'status' => $status,
            'subscriberCount' => count($subscribers),
            'subscribers' => $subscribers,
        ];
    }

    private function resolveSaasRolloutState(): ?array
    {
        $path = $this->resolveSaasRolloutStateFile();
        if (!is_file($path)) {
            return null;
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        $active = ($decoded['active'] ?? false) === true;
        $endAt = trim((string) ($decoded['windowEndsAt'] ?? ''));
        if ($active && $endAt !== '') {
            try {
                if (new \DateTimeImmutable() > new \DateTimeImmutable($endAt)) {
                    $active = false;
                }
            } catch (\Throwable) {
                $active = false;
            }
        }

        return [
            'active' => $active,
            'accessMode' => trim((string) ($decoded['accessMode'] ?? 'warning')) ?: 'warning',
            'title' => trim((string) ($decoded['title'] ?? 'Atualizacao SaaS em andamento')) ?: 'Atualizacao SaaS em andamento',
            'message' => trim((string) ($decoded['message'] ?? 'Uma atualizacao SaaS esta em andamento para este ambiente.')) ?: 'Uma atualizacao SaaS esta em andamento para este ambiente.',
            'releaseVersion' => trim((string) ($decoded['releaseVersion'] ?? '')),
            'windowStartsAt' => trim((string) ($decoded['windowStartsAt'] ?? '')) ?: null,
            'windowEndsAt' => $endAt !== '' ? $endAt : null,
            'batchCode' => trim((string) ($decoded['batchCode'] ?? '')) ?: null,
            'subscriberCode' => trim((string) ($decoded['subscriberCode'] ?? '')) ?: null,
        ];
    }

    private function resolveSaasRolloutStateFile(): string
    {
        $configured = trim((string) ($_SERVER['APP_SAAS_ROLLOUT_STATE_FILE'] ?? $_ENV['APP_SAAS_ROLLOUT_STATE_FILE'] ?? getenv('APP_SAAS_ROLLOUT_STATE_FILE') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        return dirname(__DIR__, 2) . '/var/runtime/saas-rollout-state.json';
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

    private function processOverlayUpdatePipeline(array $impactReport, string $releaseVersion): array
    {
        $programs = array_values(array_map(function (array $program) use ($releaseVersion): array {
            $impacts = array_values(array_map(function (array $impact) use ($releaseVersion): array {
                $status = (string) ($impact['status'] ?? '');
                $overlayId = (int) ($impact['overlayId'] ?? 0);
                if ($overlayId <= 0) {
                    $impact['pipelineStatus'] = 'ignored';
                    return $impact;
                }

                if ($status === 'rebase_ok') {
                    try {
                        $draft = $this->overlayService->ensureRebaseDraftForPublishedOverlay($overlayId, $releaseVersion);
                        $impact['pipelineStatus'] = (string) ($draft['status'] ?? 'draft_created');
                        $impact['pipelineMessage'] = ($draft['status'] ?? '') === 'draft_exists'
                            ? 'Rascunho de rebase ja existente para esta release.'
                            : 'Rascunho de rebase criado pela esteira do update.';
                        $impact['pipelineDraftOverlayVersionId'] = $draft['draftOverlayVersionId'] ?? null;
                        $impact['pipelinePreview'] = $draft['preview'] ?? null;
                    } catch (\Throwable $error) {
                        $impact['pipelineStatus'] = 'pipeline_failed';
                        $impact['pipelineMessage'] = $error->getMessage();
                    }
                    return $impact;
                }

                $impact['pipelineStatus'] = match ($status) {
                    'rebase_warning' => 'review_required',
                    'rebase_blocked' => 'blocked',
                    'custom_frozen' => 'frozen',
                    'missing_published_overlay' => 'missing_version',
                    default => 'ignored',
                };
                $impact['pipelineMessage'] = match ($impact['pipelineStatus']) {
                    'review_required' => 'Overlay exige revisao humana antes do rebase.',
                    'blocked' => 'Overlay bloqueado para rebase automatico nesta release.',
                    'frozen' => 'Variante completa continua congelada.',
                    'missing_version' => 'Overlay sem versao publicada para gerar rascunho.',
                    default => 'Sem acao automatica nesta release.',
                };

                return $impact;
            }, (array) ($program['overlayImpacts'] ?? [])));

            $program['overlayImpacts'] = $impacts;

            return $program;
        }, (array) ($impactReport['programs'] ?? [])));

        $summary = [
            'draftCreated' => 0,
            'draftExists' => 0,
            'reviewRequired' => 0,
            'blocked' => 0,
            'frozen' => 0,
            'missingVersion' => 0,
            'pipelineFailed' => 0,
        ];
        foreach ($programs as $program) {
            foreach ((array) ($program['overlayImpacts'] ?? []) as $impact) {
                $pipelineStatus = (string) ($impact['pipelineStatus'] ?? '');
                match ($pipelineStatus) {
                    'draft_created' => $summary['draftCreated']++,
                    'draft_exists' => $summary['draftExists']++,
                    'review_required' => $summary['reviewRequired']++,
                    'blocked' => $summary['blocked']++,
                    'frozen' => $summary['frozen']++,
                    'missing_version' => $summary['missingVersion']++,
                    'pipeline_failed' => $summary['pipelineFailed']++,
                    default => null,
                };
            }
        }

        $impactReport['programs'] = $programs;
        $impactReport['overlayPipelineSummary'] = $summary;

        return $impactReport;
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
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];

        return [
            'code' => $subscriber->getCode(),
            'name' => $subscriber->getName(),
            'document' => $subscriber->getDocument(),
            'deploymentMode' => (string) ($deployment['mode'] ?? 'dedicated_stack'),
            'runtimeEnvironmentCode' => (string) ($deployment['runtimeEnvironmentCode'] ?? ''),
            'primaryEnvironmentCode' => (string) ($deployment['primaryEnvironmentCode'] ?? ''),
            'sharedRuntimeEnvironment' => ($deployment['sharedRuntimeEnvironment'] ?? false) === true,
            'updateChannel' => (string) ($provisioning['updateChannel'] ?? 'stable'),
            'databaseEnvironment' => (string) ($provisioning['databaseEnvironment'] ?? ''),
            'databaseIdentity' => (string) ($provisioning['databaseIdentity'] ?? ''),
        ];
    }

    private function buildSubscriberDeploymentRule(?string $targetSubscriberCode, array $release, array $scenarioBehavior): array
    {
        $subscriber = $this->resolveTargetSubscriber($targetSubscriberCode, false);
        if (!$subscriber) {
            return [
                'mode' => $this->deploymentMode->resolve(),
                'applyScope' => $this->deploymentMode->resolve() === 'onprem' ? 'remote_instance' : 'saas_runtime',
                'supportsPerTenantActivation' => $this->deploymentMode->resolve() !== 'onprem',
                'sharedRuntimeSubscriberCount' => 0,
                'runtimeEnvironmentCode' => null,
                'requiresSharedRuntimeCoordination' => false,
            ];
        }

        $metadata = $subscriber->getMetadata();
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
        $mode = strtolower(trim((string) ($deployment['mode'] ?? 'dedicated_stack'))) ?: 'dedicated_stack';
        $runtimeEnvironmentCode = trim((string) ($deployment['runtimeEnvironmentCode'] ?? ''));
        $sharedCodes = $this->resolveSharedRuntimeSubscriberCodes($runtimeEnvironmentCode);
        $supportsPerTenantActivation = !in_array($mode, ['shared_program_shared_db', 'shared_program_dedicated_db'], true);

        return [
            'mode' => $mode,
            'applyScope' => match ($mode) {
                'shared_program_shared_db' => 'runtime_environment',
                'shared_program_dedicated_db' => 'subscriber_database',
                'onprem_remote' => 'remote_instance',
                default => 'subscriber_stack',
            },
            'rolloutScope' => match ($mode) {
                'shared_program_shared_db' => 'runtime_environment',
                'shared_program_dedicated_db' => 'shared_application',
                'onprem_remote' => 'remote_instance',
                default => 'subscriber_stack',
            },
            'consentScope' => $supportsPerTenantActivation ? 'subscriber' : 'runtime_environment',
            'supportsPerTenantActivation' => $supportsPerTenantActivation,
            'runtimeEnvironmentCode' => $runtimeEnvironmentCode !== '' ? $runtimeEnvironmentCode : null,
            'sharedRuntimeSubscriberCount' => count($sharedCodes),
            'sharedRuntimeSubscriberCodes' => $sharedCodes,
            'requiresSharedRuntimeCoordination' => !$supportsPerTenantActivation && count($sharedCodes) > 1,
            'entryBlockAllowed' => (string) ($release['category'] ?? '') === 'security_critical'
                || (string) ($scenarioBehavior['entryBlockAllowed'] ?? false) === '1'
                || ($scenarioBehavior['entryBlockAllowed'] ?? false) === true,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveSharedRuntimeSubscriberCodes(string $runtimeEnvironmentCode): array
    {
        $runtimeCode = trim($runtimeEnvironmentCode);
        if ($runtimeCode === '') {
            return [];
        }

        $codes = [];
        foreach ($this->subscribers->findEnabledOrdered() as $subscriber) {
            $metadata = $subscriber->getMetadata();
            $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
            if (trim((string) ($deployment['runtimeEnvironmentCode'] ?? '')) !== $runtimeCode) {
                continue;
            }
            $codes[] = $subscriber->getCode();
        }

        return array_values(array_unique(array_filter($codes, static fn (string $value): bool => trim($value) !== '')));
    }

    private function resolveSharedRuntimeActivationStatus(string $version, array $deploymentRule): string
    {
        $codes = array_values(array_filter(array_map('strval', (array) ($deploymentRule['sharedRuntimeSubscriberCodes'] ?? [])), static fn (string $value): bool => trim($value) !== ''));
        if (!$codes) {
            return 'pending';
        }

        foreach ($codes as $subscriberCode) {
            $activation = $this->resolveActivation($version, $subscriberCode);
            if (!$activation || $activation->getStatus() !== 'enabled') {
                return 'pending';
            }
        }

        return 'enabled';
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

    private function buildOrchestratorPayload(SystemUpdateRelease $release, array $evaluation, ?AuthSubscriber $targetSubscriber = null, ?array $package = null, ?SystemUpdateExecution $execution = null, ?array $rolloutPlan = null, ?array $rolloutBatch = null): array
    {
        $environment = $this->environmentIdentity->resolve();
        $metadata = $release->getMetadata();
        $resolvedPlan = is_array($rolloutPlan) ? $rolloutPlan : $this->buildRolloutPlan($release->getVersion(), $targetSubscriber?->getCode());

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
            'entryAccessMode' => (string) (($resolvedPlan['entryBlockPlan']['accessMode'] ?? 'warning')),
            'steps' => $release->getSteps(),
            'package' => $package,
            'rolloutWindow' => $resolvedPlan['rolloutWindow'] ?? null,
            'rolloutBatch' => $rolloutBatch,
            'impactReport' => $evaluation['impactReport'] ?? [],
        ];
    }
}
