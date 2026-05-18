<?php

namespace App\Provisioning;

use App\Entity\AuthSubscriber;
use App\Entity\RuntimeAsyncJob;
use App\Repository\AuthSubscriberRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Repository\SystemUpdateExecutionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAsyncJobService;
use App\Runtime\CentralControlResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberProvisioningService
{
    private const DEPLOYMENT_MODES = [
        'shared_program_shared_db',
        'shared_program_dedicated_db',
        'dedicated_stack',
        'onprem_remote',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthSubscriberRepository $subscribers,
        private readonly BuilderEntityRepository $entities,
        private readonly ProgramRepository $programs,
        private readonly RuntimeAsyncJobRepository $jobs,
        private readonly SystemUpdateExecutionRepository $executions,
        private readonly RuntimeAsyncJobService $asyncJobs,
        private readonly StructuralIntegrityService $integrity,
        private readonly PermissionResolver $permissions,
        private readonly CentralControlResolver $central,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
        private readonly OnPremPackageBuilderService $packages,
    ) {
    }

    public function bootstrap(): array
    {
        $subscribers = $this->central->isCentralControl() ? $this->listSubscribers() : [];
        $runtimeEnvironments = $this->central->isCentralControl() ? $this->buildRuntimeEnvironmentAudit($subscribers) : [];
        $operationalMatrix = $this->central->isCentralControl() ? $this->buildOperationalMatrix($subscribers, $runtimeEnvironments) : [];

        return [
            'centralControl' => $this->central->resolve(),
            'environment' => $this->environmentIdentity->resolve(),
            'subscribers' => $this->central->isCentralControl() ? $this->enrichSubscribersWithRuntimeUsage($subscribers, $runtimeEnvironments, $operationalMatrix) : [],
            'jobs' => $this->central->isCentralControl() ? $this->listProvisionJobs() : [],
            'runtimeEnvironments' => $runtimeEnvironments,
            'operationalMatrix' => $operationalMatrix,
            'isolationCatalog' => $this->central->isCentralControl() ? $this->buildIsolationCatalog() : [
                'summary' => [
                    'globalTables' => 0,
                    'subscriberTables' => 0,
                    'riskTables' => 0,
                ],
                'items' => [],
            ],
        ];
    }

    public function saveSubscriber(array $payload): array
    {
        $code = $this->normalizeRequired($payload['code'] ?? '');
        $name = $this->normalizeRequired($payload['name'] ?? '');
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('Informe pelo menos codigo e nome do assinante.');
        }

        $principal = (bool) ($payload['principal'] ?? false);
        $enabled = ($payload['enabled'] ?? true) !== false;
        $subscriber = $this->subscribers->findOneBy(['code' => $code]) ?? new AuthSubscriber();

        if ($principal) {
            foreach ($this->subscribers->findAll() as $item) {
                if ($item->getCode() !== $code && $item->isPrincipal()) {
                    $item->setPrincipal(false);
                    $this->entityManager->persist($item);
                    if ($item->getId()) {
                        $this->integrity->signAuthSubscriber($item, ['source' => 'subscriber-provisioning.demotePrincipal']);
                    }
                }
            }
        }

        $metadata = $subscriber->getMetadata();
        $metadata['source'] = 'subscriber-provisioning';
        $metadata['deployment'] = $this->normalizeDeploymentMetadata($subscriber->getCode(), $payload, $metadata);
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
        $metadata['provisioning'] = [
            'instanceCode' => $this->normalizeOptional($payload['instanceCode'] ?? ''),
            'databaseEnvironment' => $this->normalizeOptional($payload['databaseEnvironment'] ?? ''),
            'databaseIdentity' => $this->normalizeOptional($payload['databaseIdentity'] ?? ''),
            'databaseName' => $this->normalizeOptional($payload['databaseName'] ?? ''),
            'updateChannel' => $this->normalizeUpdateChannel((string) ($payload['updateChannel'] ?? ($metadata['provisioning']['updateChannel'] ?? 'stable'))),
            'adminUsername' => $this->normalizeOptional($payload['adminUsername'] ?? ''),
            'adminDisplayName' => $this->normalizeOptional($payload['adminDisplayName'] ?? ''),
            'adminEmail' => $this->normalizeOptional($payload['adminEmail'] ?? ''),
            'runtimeEnvironmentCode' => (string) ($deployment['runtimeEnvironmentCode'] ?? ''),
            'primaryEnvironmentCode' => (string) ($deployment['primaryEnvironmentCode'] ?? ''),
        ];

        $subscriber
            ->setCode($code)
            ->setName($name)
            ->setDocument($this->normalizeNullable($payload['document'] ?? null))
            ->setPrincipal($principal)
            ->setEnabled($enabled)
            ->setMetadata($metadata);

        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();
        $this->integrity->signAuthSubscriber($subscriber, ['source' => 'subscriber-provisioning.save']);
        $this->entityManager->flush();

        return [
            'subscriber' => $this->formatSubscriber($subscriber),
        ];
    }

    public function queueProvision(array $payload): array
    {
        $subscriberCode = $this->normalizeRequired($payload['subscriberCode'] ?? $payload['code'] ?? '');
        if ($subscriberCode === '') {
            throw new \InvalidArgumentException('Informe o assinante para provisionar.');
        }

        $subscriber = $this->subscribers->findEnabledByCode($subscriberCode) ?? $this->subscribers->findOneBy(['code' => $subscriberCode]);
        if (!$subscriber) {
            throw new \RuntimeException('Assinante nao encontrado para provisionamento.');
        }

        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
        $deploymentMode = $this->normalizeDeploymentMode((string) ($deployment['mode'] ?? ''));
        $runtimeEnvironmentCode = $this->normalizeOptional($payload['runtimeEnvironmentCode'] ?? $provisioning['runtimeEnvironmentCode'] ?? $deployment['runtimeEnvironmentCode'] ?? '') ?: $this->defaultRuntimeEnvironmentCode($subscriber->getCode(), $deploymentMode);
        $instanceCode = $this->normalizeOptional($payload['instanceCode'] ?? $provisioning['instanceCode'] ?? '') ?: ('construtor-pg-' . $runtimeEnvironmentCode);
        $databaseEnvironment = $this->normalizeOptional($payload['databaseEnvironment'] ?? $provisioning['databaseEnvironment'] ?? '') ?: 'prod';
        $databaseIdentity = $this->normalizeOptional($payload['databaseIdentity'] ?? $provisioning['databaseIdentity'] ?? '') ?: ('saas:' . $runtimeEnvironmentCode);
        $databaseName = $this->normalizeOptional($payload['databaseName'] ?? $provisioning['databaseName'] ?? '') ?: ('construtor_pg_' . str_replace('-', '_', strtolower($runtimeEnvironmentCode)));
        $adminUsername = $this->normalizeOptional($payload['adminUsername'] ?? $provisioning['adminUsername'] ?? '') ?: 'admin';
        $adminDisplayName = $this->normalizeOptional($payload['adminDisplayName'] ?? $provisioning['adminDisplayName'] ?? '') ?: ('Administrador ' . $subscriber->getName());
        $adminPassword = $this->normalizeOptional($payload['adminPassword'] ?? '');

        $this->asyncJobs->schedule('subscriber.environment.provision', [
            'subscriberCode' => $subscriber->getCode(),
            'subscriberName' => $subscriber->getName(),
            'subscriberDocument' => $subscriber->getDocument(),
            'instanceCode' => $instanceCode,
            'databaseEnvironment' => $databaseEnvironment,
            'databaseIdentity' => $databaseIdentity,
            'databaseName' => $databaseName,
            'deploymentMode' => $deploymentMode,
            'runtimeEnvironmentCode' => $runtimeEnvironmentCode,
            'primaryEnvironmentCode' => $this->normalizeOptional($payload['primaryEnvironmentCode'] ?? $provisioning['primaryEnvironmentCode'] ?? $deployment['primaryEnvironmentCode'] ?? '') ?: $this->defaultPrimaryEnvironmentCode($subscriber->getCode()),
            'updateChannel' => $this->normalizeUpdateChannel((string) ($payload['updateChannel'] ?? $provisioning['updateChannel'] ?? 'stable')),
            'adminUsername' => $adminUsername,
            'adminDisplayName' => $adminDisplayName,
            'adminPassword' => $adminPassword,
        ], [
            'screenId' => 'admin.assinante-ambientes',
            'programId' => 'admin-assinante-ambientes',
            'actionId' => 'provisionSubscriberEnvironment',
            'entityCode' => 'auth_subscriber',
            'recordId' => $subscriber->getCode(),
            'message' => 'Provisionamento do assinante enfileirado.',
        ]);

        $queued = $this->asyncJobs->flushPending();
        $jobId = (int) (($queued[0]['id'] ?? 0));
        $job = $jobId > 0 ? $this->jobs->find($jobId) : null;

        return [
            'queued' => $queued,
            'job' => $job ? $this->formatJob($job) : null,
        ];
    }

    public function listSubscribers(): array
    {
        return array_map(fn (AuthSubscriber $subscriber): array => $this->formatSubscriber($subscriber), $this->subscribers->findEnabledOrdered());
    }

    public function listProvisionJobs(?string $subscriberCode = null, int $limit = 20): array
    {
        $items = $this->jobs->findRecentByJobType('subscriber.environment.provision', $limit);
        $normalizedCode = $this->normalizeOptional($subscriberCode);
        $result = [];
        foreach ($items as $job) {
            $formatted = $this->formatJob($job);
            if ($normalizedCode !== '' && ($formatted['subscriberCode'] ?? '') !== $normalizedCode) {
                continue;
            }
            $result[] = $formatted;
        }

        return $result;
    }

    public function getProvisionJob(int $jobId): array
    {
        $job = $this->jobs->find($jobId);
        if (!$job || $job->getJobType() !== 'subscriber.environment.provision') {
            throw new \RuntimeException('Job de provisionamento nao encontrado.');
        }

        return $this->formatJob($job);
    }

    public function buildOnPremPackage(array $payload): array
    {
        $subscriberCode = $this->normalizeRequired($payload['subscriberCode'] ?? '');
        if ($subscriberCode === '') {
            throw new \InvalidArgumentException('Informe o assinante para gerar o pacote on-premise.');
        }

        $subscriber = $this->subscribers->findOneBy(['code' => $subscriberCode]);
        if (!$subscriber) {
            throw new \RuntimeException('Assinante nao encontrado para gerar pacote on-premise.');
        }

        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
        $deploymentMode = $this->normalizeDeploymentMode((string) ($deployment['mode'] ?? ''));
        $runtimeEnvironmentCode = $this->normalizeOptional($payload['runtimeEnvironmentCode'] ?? $provisioning['runtimeEnvironmentCode'] ?? $deployment['runtimeEnvironmentCode'] ?? '') ?: $this->defaultRuntimeEnvironmentCode($subscriber->getCode(), $deploymentMode);
        $package = $this->packages->build([
            'subscriberCode' => $subscriber->getCode(),
            'subscriberName' => $subscriber->getName(),
            'subscriberDocument' => $subscriber->getDocument(),
            'databaseEnvironment' => $this->normalizeOptional($payload['databaseEnvironment'] ?? $provisioning['databaseEnvironment'] ?? '') ?: 'prod',
            'databaseIdentity' => $this->normalizeOptional($payload['databaseIdentity'] ?? $provisioning['databaseIdentity'] ?? '') ?: ('onprem:' . $runtimeEnvironmentCode),
            'databaseName' => $this->normalizeOptional($payload['databaseName'] ?? $provisioning['databaseName'] ?? '') ?: ('construtor_pg_' . str_replace('-', '_', strtolower($runtimeEnvironmentCode))),
            'updateChannel' => $this->normalizeUpdateChannel((string) ($payload['updateChannel'] ?? $provisioning['updateChannel'] ?? 'stable')),
            'adminUsername' => $this->normalizeOptional($payload['adminUsername'] ?? $provisioning['adminUsername'] ?? '') ?: 'admin',
            'adminDisplayName' => $this->normalizeOptional($payload['adminDisplayName'] ?? $provisioning['adminDisplayName'] ?? '') ?: ('Administrador ' . $subscriber->getName()),
            'instanceCode' => $this->normalizeOptional($payload['instanceCode'] ?? $provisioning['instanceCode'] ?? '') ?: ('construtor-pg-' . $runtimeEnvironmentCode),
            'generatedBy' => $this->permissions->getUserId(),
        ]);

        return $package;
    }

    private function formatSubscriber(AuthSubscriber $subscriber): array
    {
        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];
        $deployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];

        return [
            'id' => $subscriber->getId(),
            'code' => $subscriber->getCode(),
            'name' => $subscriber->getName(),
            'document' => $subscriber->getDocument(),
            'principal' => $subscriber->isPrincipal(),
            'enabled' => $subscriber->isEnabled(),
            'metadata' => $metadata,
            'deploymentMode' => (string) ($deployment['mode'] ?? ''),
            'deploymentModeLabel' => $this->deploymentModeLabel((string) ($deployment['mode'] ?? '')),
            'runtimeEnvironmentCode' => (string) ($deployment['runtimeEnvironmentCode'] ?? ''),
            'primaryEnvironmentCode' => (string) ($deployment['primaryEnvironmentCode'] ?? ''),
            'sharedRuntimeEnvironment' => ($deployment['sharedRuntimeEnvironment'] ?? false) === true,
            'principalEnvironmentIsolated' => ($deployment['principalEnvironmentIsolated'] ?? true) !== false,
            'instanceCode' => (string) ($provisioning['instanceCode'] ?? ''),
            'databaseEnvironment' => (string) ($provisioning['databaseEnvironment'] ?? ''),
            'databaseIdentity' => (string) ($provisioning['databaseIdentity'] ?? ''),
            'databaseName' => (string) ($provisioning['databaseName'] ?? ''),
            'updateChannel' => $this->normalizeUpdateChannel((string) ($provisioning['updateChannel'] ?? 'stable')),
            'adminUsername' => (string) ($provisioning['adminUsername'] ?? ''),
            'adminDisplayName' => (string) ($provisioning['adminDisplayName'] ?? ''),
            'adminEmail' => (string) ($provisioning['adminEmail'] ?? ''),
            'createdAt' => $subscriber->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $subscriber->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function formatJob(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $result = $job->getResult();

        return [
            'id' => $job->getId(),
            'jobType' => $job->getJobType(),
            'status' => $job->getStatus(),
            'attempts' => $job->getAttempts(),
            'lastError' => $job->getLastError(),
            'screenId' => $job->getScreenId(),
            'programId' => $job->getProgramId(),
            'subscriberCode' => (string) ($payload['subscriberCode'] ?? ''),
            'subscriberName' => (string) ($payload['subscriberName'] ?? ''),
            'databaseIdentity' => (string) ($payload['databaseIdentity'] ?? ''),
            'databaseEnvironment' => (string) ($payload['databaseEnvironment'] ?? ''),
            'databaseName' => (string) ($payload['databaseName'] ?? ''),
            'instanceCode' => (string) ($payload['instanceCode'] ?? ''),
            'deploymentMode' => (string) ($payload['deploymentMode'] ?? ''),
            'runtimeEnvironmentCode' => (string) ($payload['runtimeEnvironmentCode'] ?? ''),
            'primaryEnvironmentCode' => (string) ($payload['primaryEnvironmentCode'] ?? ''),
            'updateChannel' => (string) ($payload['updateChannel'] ?? ''),
            'result' => $result,
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $job->getUpdatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
        ];
    }

    private function normalizeRequired(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeOptional(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeNullable(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDeploymentMetadata(string $subscriberCode, array $payload, array $existingMetadata): array
    {
        $existing = is_array($existingMetadata['deployment'] ?? null) ? $existingMetadata['deployment'] : [];
        $mode = $this->normalizeDeploymentMode((string) ($payload['deploymentMode'] ?? $existing['mode'] ?? ''));
        $runtimeEnvironmentCode = $this->normalizeOptional($payload['runtimeEnvironmentCode'] ?? $existing['runtimeEnvironmentCode'] ?? '');
        if ($runtimeEnvironmentCode === '') {
            $runtimeEnvironmentCode = $this->defaultRuntimeEnvironmentCode($subscriberCode, $mode);
        }
        $primaryEnvironmentCode = $this->normalizeOptional($payload['primaryEnvironmentCode'] ?? $existing['primaryEnvironmentCode'] ?? '');
        if ($primaryEnvironmentCode === '') {
            $primaryEnvironmentCode = $this->defaultPrimaryEnvironmentCode($subscriberCode);
        }

        return [
            'mode' => $mode,
            'runtimeEnvironmentCode' => $runtimeEnvironmentCode,
            'primaryEnvironmentCode' => $primaryEnvironmentCode,
            'sharedRuntimeEnvironment' => $mode === 'shared_program_shared_db',
            'principalEnvironmentIsolated' => true,
        ];
    }

    private function buildRuntimeEnvironmentAudit(array $subscribers): array
    {
        if (!$subscribers) {
            return [];
        }

        $activeProgramCount = count($this->programs->findBy(['status' => 'published']));
        $grouped = [];
        foreach ($subscribers as $subscriber) {
            $runtimeEnvironmentCode = trim((string) ($subscriber['runtimeEnvironmentCode'] ?? ''));
            if ($runtimeEnvironmentCode === '') {
                continue;
            }
            $grouped[$runtimeEnvironmentCode][] = $subscriber;
        }

        $result = [];
        foreach ($grouped as $runtimeEnvironmentCode => $items) {
            $databaseEnvironments = [];
            $databaseIdentities = [];
            $deploymentModes = [];
            $latestVersions = [];
            $subscriberItems = [];

            foreach ($items as $subscriber) {
                $databaseEnvironment = trim((string) ($subscriber['databaseEnvironment'] ?? ''));
                $databaseIdentity = trim((string) ($subscriber['databaseIdentity'] ?? ''));
                $deploymentMode = trim((string) ($subscriber['deploymentMode'] ?? ''));
                $subscriberCode = trim((string) ($subscriber['code'] ?? ''));
                $latestVersion = $subscriberCode !== '' ? ($this->executions->findLatestSuccessfulVersionBySubscriber($subscriberCode) ?? '') : '';

                if ($databaseEnvironment !== '') {
                    $databaseEnvironments[$databaseEnvironment] = true;
                }
                if ($databaseIdentity !== '') {
                    $databaseIdentities[$databaseIdentity] = true;
                }
                if ($deploymentMode !== '') {
                    $deploymentModes[$deploymentMode] = true;
                }
                if ($latestVersion !== '') {
                    $latestVersions[$latestVersion] = true;
                }

                $subscriberItems[] = [
                    'code' => $subscriberCode,
                    'name' => (string) ($subscriber['name'] ?? ''),
                    'deploymentMode' => $deploymentMode,
                    'updateChannel' => (string) ($subscriber['updateChannel'] ?? 'stable'),
                    'latestSuccessfulVersion' => $latestVersion,
                ];
            }

            $divergences = [];
            if (count($databaseEnvironments) > 1) {
                $divergences[] = 'Ambiente de banco divergente entre assinantes do mesmo runtime.';
            }
            if (count($databaseIdentities) > 1) {
                $divergences[] = 'Identidade de banco divergente entre assinantes do mesmo runtime.';
            }
            if (count($deploymentModes) > 1) {
                $divergences[] = 'Modelos de deployment diferentes apontando para o mesmo runtime.';
            }
            if (count($latestVersions) > 1) {
                $divergences[] = 'Historico de versoes aplicado diverge entre assinantes do mesmo runtime.';
            }

            $result[] = [
                'runtimeEnvironmentCode' => $runtimeEnvironmentCode,
                'sharedRuntime' => count($items) > 1 || in_array('shared_program_shared_db', array_keys($deploymentModes), true),
                'subscriberCount' => count($items),
                'subscribers' => $subscriberItems,
                'deploymentModes' => array_values(array_keys($deploymentModes)),
                'databaseEnvironments' => array_values(array_keys($databaseEnvironments)),
                'databaseIdentities' => array_values(array_keys($databaseIdentities)),
                'latestSuccessfulVersions' => array_values(array_keys($latestVersions)),
                'activeProgramCount' => $activeProgramCount,
                'divergences' => $divergences,
            ];
        }

        usort($result, static fn (array $left, array $right): int => strcmp((string) $left['runtimeEnvironmentCode'], (string) $right['runtimeEnvironmentCode']));

        return $result;
    }

    private function buildIsolationCatalog(): array
    {
        $items = [];
        $summary = [
            'globalTables' => 0,
            'subscriberTables' => 0,
            'riskTables' => 0,
        ];

        foreach ($this->entities->findBy([], ['name' => 'ASC']) as $entity) {
            if ($entity->getEntityType() !== 'persistence') {
                continue;
            }

            $metadata = $entity->getMetadata();
            $subscriberIsolation = is_array($metadata['subscriberIsolation'] ?? null) ? $metadata['subscriberIsolation'] : [];
            $mode = trim((string) ($subscriberIsolation['mode'] ?? 'none')) ?: 'none';
            $columnName = trim((string) ($subscriberIsolation['columnName'] ?? ''));
            $globalTable = ($subscriberIsolation['globalTable'] ?? false) === true;
            $scopeLabel = 'Global';
            $riskStatus = 'ok';
            $riskMessage = '';

            if ($mode === 'subscriber_column') {
                $summary['subscriberTables']++;
                $scopeLabel = 'Filtrada por assinante';
                if ($columnName === '') {
                    $riskStatus = 'warning';
                    $riskMessage = 'Coluna do assinante ausente nos metadados.';
                    $summary['riskTables']++;
                }
            } else {
                $summary['globalTables']++;
                if (!$globalTable) {
                    $riskStatus = 'warning';
                    $riskMessage = 'Tabela global sem confirmacao explicita no builder.';
                    $summary['riskTables']++;
                }
            }

            $items[] = [
                'entityCode' => $entity->getCode(),
                'name' => $entity->getName(),
                'tableName' => (string) $entity->getTableName(),
                'scopeLabel' => $scopeLabel,
                'subscriberIsolationMode' => $mode,
                'subscriberColumnName' => $columnName !== '' ? $columnName : null,
                'globalTable' => $globalTable,
                'riskStatus' => $riskStatus,
                'riskMessage' => $riskMessage,
            ];
        }

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    private function buildOperationalMatrix(array $subscribers, array $runtimeEnvironments): array
    {
        $sharedCountByRuntime = [];
        foreach ($runtimeEnvironments as $runtimeEnvironment) {
            $sharedCountByRuntime[(string) ($runtimeEnvironment['runtimeEnvironmentCode'] ?? '')] = (int) ($runtimeEnvironment['subscriberCount'] ?? 0);
        }

        $globalLatestVersion = $this->executions->findLatestSuccessfulVersion();
        $matrix = [];
        foreach ($subscribers as $subscriber) {
            $runtimeEnvironmentCode = trim((string) ($subscriber['runtimeEnvironmentCode'] ?? ''));
            $subscriberCode = trim((string) ($subscriber['code'] ?? ''));
            $latestSuccessfulVersion = $subscriberCode !== '' ? ($this->executions->findLatestSuccessfulVersionBySubscriber($subscriberCode) ?? '') : '';
            $versionStatus = $latestSuccessfulVersion === ''
                ? 'sem-historico'
                : (($globalLatestVersion ?? '') !== '' && version_compare($latestSuccessfulVersion, (string) $globalLatestVersion, '<') ? 'defasado' : 'atual');

            $matrix[] = [
                'code' => $subscriberCode,
                'name' => (string) ($subscriber['name'] ?? ''),
                'deploymentMode' => (string) ($subscriber['deploymentMode'] ?? ''),
                'deploymentModeLabel' => (string) ($subscriber['deploymentModeLabel'] ?? ''),
                'primaryEnvironmentCode' => (string) ($subscriber['primaryEnvironmentCode'] ?? ''),
                'runtimeEnvironmentCode' => $runtimeEnvironmentCode,
                'sharedRuntimeSubscriberCount' => (int) ($sharedCountByRuntime[$runtimeEnvironmentCode] ?? 0),
                'updateChannel' => (string) ($subscriber['updateChannel'] ?? 'stable'),
                'databaseEnvironment' => (string) ($subscriber['databaseEnvironment'] ?? ''),
                'databaseIdentity' => (string) ($subscriber['databaseIdentity'] ?? ''),
                'latestSuccessfulVersion' => $latestSuccessfulVersion,
                'versionStatus' => $versionStatus,
            ];
        }

        return $matrix;
    }

    private function enrichSubscribersWithRuntimeUsage(array $subscribers, array $runtimeEnvironments, array $operationalMatrix): array
    {
        $sharedCountByRuntime = [];
        foreach ($runtimeEnvironments as $runtimeEnvironment) {
            $sharedCountByRuntime[(string) ($runtimeEnvironment['runtimeEnvironmentCode'] ?? '')] = (int) ($runtimeEnvironment['subscriberCount'] ?? 0);
        }

        $matrixBySubscriber = [];
        foreach ($operationalMatrix as $item) {
            $matrixBySubscriber[(string) ($item['code'] ?? '')] = $item;
        }

        return array_map(function (array $subscriber) use ($sharedCountByRuntime, $matrixBySubscriber): array {
            $runtimeEnvironmentCode = trim((string) ($subscriber['runtimeEnvironmentCode'] ?? ''));
            $code = trim((string) ($subscriber['code'] ?? ''));
            $matrix = $matrixBySubscriber[$code] ?? [];
            $subscriber['runtimeSubscriberCount'] = (int) ($sharedCountByRuntime[$runtimeEnvironmentCode] ?? 0);
            $subscriber['latestSuccessfulVersion'] = (string) ($matrix['latestSuccessfulVersion'] ?? '');
            $subscriber['versionStatus'] = (string) ($matrix['versionStatus'] ?? 'sem-historico');

            return $subscriber;
        }, $subscribers);
    }

    private function normalizeDeploymentMode(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, self::DEPLOYMENT_MODES, true)) {
            return 'dedicated_stack';
        }

        return $normalized;
    }

    private function deploymentModeLabel(string $mode): string
    {
        return match ($this->normalizeDeploymentMode($mode)) {
            'shared_program_shared_db' => 'Programa e banco compartilhados por coluna de assinante',
            'shared_program_dedicated_db' => 'Programa compartilhado e banco dedicado',
            'dedicated_stack' => 'Container e banco dedicados no SaaS',
            'onprem_remote' => 'Instalacao on-premise remota',
            default => 'Container e banco dedicados no SaaS',
        };
    }

    private function normalizeUpdateChannel(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, ['stable', 'pilot', 'canary', 'lts'], true)) {
            return 'stable';
        }

        return $normalized;
    }

    private function defaultRuntimeEnvironmentCode(string $subscriberCode, string $deploymentMode): string
    {
        if ($deploymentMode === 'shared_program_shared_db') {
            return 'shared-runtime-default';
        }

        return $subscriberCode;
    }

    private function defaultPrimaryEnvironmentCode(string $subscriberCode): string
    {
        return $subscriberCode . '-principal';
    }
}
