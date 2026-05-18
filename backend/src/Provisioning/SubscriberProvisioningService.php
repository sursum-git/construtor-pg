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

    private const PROVISION_STEPS = [
        'prepare_env' => 'Preparar ambiente e variaveis',
        'start_database' => 'Subir banco dedicado',
        'bootstrap_app' => 'Bootstrap da aplicacao',
        'create_subscriber' => 'Criar assinante e admin inicial',
        'publish_defaults' => 'Publicar programas padrao',
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
        private readonly ProvisioningSecretStore $secretStore,
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
        [$subscriber, $normalizedPayload] = $this->resolveProvisionPayload($payload);
        $precheck = $this->precheckProvision($normalizedPayload);
        if (($precheck['hasBlockingIssues'] ?? false) === true) {
            throw new \RuntimeException('Existem conflitos bloqueantes no provisionamento. Revise a validacao previa antes de continuar.');
        }

        $credentialRef = $this->secretStore->store([
            'adminPassword' => (string) ($normalizedPayload['adminPassword'] ?? ''),
        ]);

        $jobPayload = $normalizedPayload;
        unset($jobPayload['adminPassword']);
        $jobPayload['credentialRef'] = $credentialRef;
        $jobPayload['precheckSnapshot'] = $precheck;
        $jobPayload['steps'] = $this->buildProvisionSteps();

        $this->asyncJobs->schedule('subscriber.environment.provision', $jobPayload, [
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
        [$subscriber, $normalizedPayload] = $this->resolveProvisionPayload($payload);
        $precheck = $this->precheckProvision(array_merge($normalizedPayload, [
            'databaseIdentity' => $normalizedPayload['databaseIdentity'] ?: ('onprem:' . $normalizedPayload['runtimeEnvironmentCode']),
        ]));
        $package = $this->packages->build(array_merge($normalizedPayload, [
            'subscriberCode' => $subscriber->getCode(),
            'subscriberName' => $subscriber->getName(),
            'subscriberDocument' => $subscriber->getDocument(),
            'databaseIdentity' => $normalizedPayload['databaseIdentity'] ?: ('onprem:' . $normalizedPayload['runtimeEnvironmentCode']),
            'generatedBy' => $this->permissions->getUserId(),
        ]));
        $package['precheck'] = $precheck;

        return $package;
    }

    public function precheckProvision(array $payload): array
    {
        [, $normalizedPayload] = $this->resolveProvisionPayload($payload);
        $currentSubscriberCode = (string) ($normalizedPayload['subscriberCode'] ?? '');
        $deploymentMode = (string) ($normalizedPayload['deploymentMode'] ?? 'dedicated_stack');
        $runtimeEnvironmentCode = (string) ($normalizedPayload['runtimeEnvironmentCode'] ?? '');
        $primaryEnvironmentCode = (string) ($normalizedPayload['primaryEnvironmentCode'] ?? '');
        $databaseIdentity = (string) ($normalizedPayload['databaseIdentity'] ?? '');
        $databaseName = (string) ($normalizedPayload['databaseName'] ?? '');
        $instanceCode = (string) ($normalizedPayload['instanceCode'] ?? '');
        $adminPassword = (string) ($normalizedPayload['adminPassword'] ?? '');
        $passwordCheck = $this->evaluateAdminPassword($adminPassword, $currentSubscriberCode, (string) ($normalizedPayload['adminUsername'] ?? ''));

        $conflicts = [];
        $warnings = [];
        $allowSharedRuntime = $deploymentMode === 'shared_program_shared_db';
        foreach ($this->subscribers->findEnabledOrdered() as $subscriber) {
            if ($subscriber->getCode() === $currentSubscriberCode) {
                continue;
            }
            $metadata = $subscriber->getMetadata();
            $existingProvisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];
            $existingDeployment = is_array($metadata['deployment'] ?? null) ? $metadata['deployment'] : [];
            $existingRuntime = trim((string) ($existingDeployment['runtimeEnvironmentCode'] ?? ''));
            $existingPrimary = trim((string) ($existingDeployment['primaryEnvironmentCode'] ?? ''));
            $existingInstance = trim((string) ($existingProvisioning['instanceCode'] ?? ''));
            $existingDatabaseIdentity = trim((string) ($existingProvisioning['databaseIdentity'] ?? ''));
            $existingDatabaseName = trim((string) ($existingProvisioning['databaseName'] ?? ''));
            $existingMode = $this->normalizeDeploymentMode((string) ($existingDeployment['mode'] ?? ''));

            if ($primaryEnvironmentCode !== '' && $existingPrimary === $primaryEnvironmentCode) {
                $conflicts[] = $this->conflictItem('primary_environment_conflict', 'Outro assinante ja usa o mesmo ambiente principal isolado.', $subscriber->getCode());
            }
            if ($runtimeEnvironmentCode !== '' && $existingRuntime === $runtimeEnvironmentCode && (!$allowSharedRuntime || $existingMode !== 'shared_program_shared_db')) {
                $conflicts[] = $this->conflictItem('runtime_environment_conflict', 'O ambiente runtime informado ja esta reservado por assinante com modo nao compartilhado.', $subscriber->getCode());
            }
            if ($instanceCode !== '' && $existingInstance === $instanceCode && (!$allowSharedRuntime || $existingRuntime !== $runtimeEnvironmentCode)) {
                $conflicts[] = $this->conflictItem('instance_code_conflict', 'Outro assinante ja usa o mesmo instance code.', $subscriber->getCode());
            }
            if ($databaseIdentity !== '' && $existingDatabaseIdentity === $databaseIdentity && (!$allowSharedRuntime || $existingRuntime !== $runtimeEnvironmentCode)) {
                $conflicts[] = $this->conflictItem('database_identity_conflict', 'Outra configuracao ja usa a mesma identidade de banco.', $subscriber->getCode());
            }
            if ($databaseName !== '' && $existingDatabaseName === $databaseName && (!$allowSharedRuntime || $existingRuntime !== $runtimeEnvironmentCode)) {
                $conflicts[] = $this->conflictItem('database_name_conflict', 'Outra configuracao ja usa o mesmo nome de banco.', $subscriber->getCode());
            }
        }

        if ($deploymentMode === 'onprem_remote') {
            $warnings[] = [
                'code' => 'onprem_remote',
                'message' => 'O modo on-premise remoto normalmente depende mais do pacote instalavel e do updater remoto do que do job SaaS central.',
            ];
        }

        $checklist = [
            $this->checklistItem('central_control', 'Sistema central SaaS habilitado', $this->central->isCentralControl() ? 'ok' : 'error', $this->central->isCentralControl() ? 'Tela liberada para o sistema central.' : 'Provisionamento central so deve rodar quando APP_SYSTEM_ROLE=saas_central ou APP_CENTRAL_CONTROL_ENABLED=1.'),
            $this->checklistItem('worker', 'Worker de jobs disponivel', 'manual', 'O worker precisa estar ativo para consumir subscriber.environment.provision.'),
            $this->checklistItem('deployment_mode', 'Modelo de deployment coerente', in_array($deploymentMode, self::DEPLOYMENT_MODES, true) ? 'ok' : 'error', $this->deploymentModeLabel($deploymentMode)),
            $this->checklistItem('runtime_environment', 'Ambiente runtime definido', $runtimeEnvironmentCode !== '' ? 'ok' : 'error', $runtimeEnvironmentCode !== '' ? ('Runtime: ' . $runtimeEnvironmentCode) : 'Informe o ambiente runtime.'),
            $this->checklistItem('primary_environment', 'Ambiente principal isolado definido', $primaryEnvironmentCode !== '' ? 'ok' : 'error', $primaryEnvironmentCode !== '' ? ('Principal: ' . $primaryEnvironmentCode) : 'Informe o ambiente principal isolado.'),
            $this->checklistItem('admin_password', 'Credencial inicial forte', $passwordCheck['status'], $passwordCheck['message']),
            $this->checklistItem('zip_archive', 'ZipArchive disponivel para pacote on-premise', class_exists(\ZipArchive::class) ? 'ok' : 'warning', class_exists(\ZipArchive::class) ? 'Pacote on-premise pode ser gerado neste ambiente.' : 'ZipArchive nao esta disponivel; o pacote on-premise nao sera gerado aqui.'),
        ];

        return [
            'payload' => $normalizedPayload,
            'steps' => $this->buildProvisionSteps(),
            'hasBlockingIssues' => $conflicts !== [] || $passwordCheck['status'] === 'error' || !$this->central->isCentralControl(),
            'blockingIssues' => array_merge(
                $conflicts,
                $passwordCheck['status'] === 'error' ? [[
                    'code' => 'weak_admin_password',
                    'message' => $passwordCheck['message'],
                ]] : [],
                !$this->central->isCentralControl() ? [[
                    'code' => 'not_central_control',
                    'message' => 'A operacao de provisionamento pertence apenas ao sistema central SaaS.',
                ]] : []
            ),
            'warnings' => $warnings,
            'checklist' => $checklist,
            'conflicts' => $conflicts,
            'credentialPolicy' => $passwordCheck,
        ];
    }

    public function retryProvisionJob(int $jobId, ?string $retryFromStep = null): array
    {
        $job = $this->jobs->find($jobId);
        if (!$job || $job->getJobType() !== 'subscriber.environment.provision') {
            throw new \RuntimeException('Job de provisionamento nao encontrado para retry.');
        }
        if (in_array($job->getStatus(), ['queued', 'running'], true)) {
            throw new \RuntimeException('O retry parcial so pode ser solicitado depois do termino do job anterior.');
        }

        $payload = $job->getPayload();
        $startStep = $retryFromStep ?: $this->detectRetryStep($job->getResult());
        if (!isset(self::PROVISION_STEPS[$startStep])) {
            throw new \RuntimeException('Step inicial do retry parcial nao e suportado.');
        }

        $payload['retryFromStep'] = $startStep;
        $payload['retryJobId'] = $jobId;
        $payload['steps'] = $this->buildProvisionSteps($startStep);

        $this->asyncJobs->schedule('subscriber.environment.provision', $payload, [
            'screenId' => 'admin.assinante-ambientes',
            'programId' => 'admin-assinante-ambientes',
            'actionId' => 'retrySubscriberEnvironmentProvision',
            'entityCode' => 'auth_subscriber',
            'recordId' => (string) ($payload['subscriberCode'] ?? ''),
            'message' => 'Retry parcial do provisionamento enfileirado.',
        ]);

        $queued = $this->asyncJobs->flushPending();
        $queuedJobId = (int) (($queued[0]['id'] ?? 0));
        $queuedJob = $queuedJobId > 0 ? $this->jobs->find($queuedJobId) : null;

        return [
            'queued' => $queued,
            'job' => $queuedJob ? $this->formatJob($queuedJob) : null,
        ];
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
            'steps' => is_array($result['steps'] ?? null) ? $result['steps'] : [],
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

    private function resolveProvisionPayload(array $payload): array
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
        $deploymentMode = $this->normalizeDeploymentMode((string) ($payload['deploymentMode'] ?? $deployment['mode'] ?? ''));
        $runtimeEnvironmentCode = $this->normalizeOptional($payload['runtimeEnvironmentCode'] ?? $provisioning['runtimeEnvironmentCode'] ?? $deployment['runtimeEnvironmentCode'] ?? '') ?: $this->defaultRuntimeEnvironmentCode($subscriber->getCode(), $deploymentMode);

        return [$subscriber, [
            'subscriberCode' => $subscriber->getCode(),
            'subscriberName' => $subscriber->getName(),
            'subscriberDocument' => $subscriber->getDocument(),
            'instanceCode' => $this->normalizeOptional($payload['instanceCode'] ?? $provisioning['instanceCode'] ?? '') ?: ('construtor-pg-' . $runtimeEnvironmentCode),
            'databaseEnvironment' => $this->normalizeOptional($payload['databaseEnvironment'] ?? $provisioning['databaseEnvironment'] ?? '') ?: 'prod',
            'databaseIdentity' => $this->normalizeOptional($payload['databaseIdentity'] ?? $provisioning['databaseIdentity'] ?? '') ?: ('saas:' . $runtimeEnvironmentCode),
            'databaseName' => $this->normalizeOptional($payload['databaseName'] ?? $provisioning['databaseName'] ?? '') ?: ('construtor_pg_' . str_replace('-', '_', strtolower($runtimeEnvironmentCode))),
            'deploymentMode' => $deploymentMode,
            'runtimeEnvironmentCode' => $runtimeEnvironmentCode,
            'primaryEnvironmentCode' => $this->normalizeOptional($payload['primaryEnvironmentCode'] ?? $provisioning['primaryEnvironmentCode'] ?? $deployment['primaryEnvironmentCode'] ?? '') ?: $this->defaultPrimaryEnvironmentCode($subscriber->getCode()),
            'updateChannel' => $this->normalizeUpdateChannel((string) ($payload['updateChannel'] ?? $provisioning['updateChannel'] ?? 'stable')),
            'adminUsername' => $this->normalizeOptional($payload['adminUsername'] ?? $provisioning['adminUsername'] ?? '') ?: 'admin',
            'adminDisplayName' => $this->normalizeOptional($payload['adminDisplayName'] ?? $provisioning['adminDisplayName'] ?? '') ?: ('Administrador ' . $subscriber->getName()),
            'adminPassword' => $this->normalizeOptional($payload['adminPassword'] ?? ''),
        ]];
    }

    private function buildProvisionSteps(?string $retryFromStep = null): array
    {
        $retryEnabled = $retryFromStep !== null && isset(self::PROVISION_STEPS[$retryFromStep]);
        $steps = [];
        foreach (self::PROVISION_STEPS as $code => $label) {
            $status = 'pending';
            if ($retryEnabled) {
                $status = $code === $retryFromStep ? 'pending' : 'reused';
                if (array_search($code, array_keys(self::PROVISION_STEPS), true) > array_search($retryFromStep, array_keys(self::PROVISION_STEPS), true)) {
                    $status = 'pending';
                }
            }
            $steps[] = [
                'code' => $code,
                'label' => $label,
                'status' => $status,
            ];
        }

        return $steps;
    }

    private function evaluateAdminPassword(string $password, string $subscriberCode, string $adminUsername): array
    {
        if ($password === '') {
            return [
                'status' => 'error',
                'message' => 'Informe uma senha inicial forte para o admin do assinante.',
            ];
        }

        $checks = [
            preg_match('/[a-z]/', $password) === 1,
            preg_match('/[A-Z]/', $password) === 1,
            preg_match('/\d/', $password) === 1,
            preg_match('/[^a-zA-Z0-9]/', $password) === 1,
            mb_strlen($password) >= 14,
            stripos($password, $subscriberCode) === false,
            stripos($password, $adminUsername) === false,
        ];

        if (in_array(false, $checks, true)) {
            return [
                'status' => 'error',
                'message' => 'A senha inicial precisa ter pelo menos 14 caracteres, maiuscula, minuscula, numero, simbolo e nao pode repetir usuario ou codigo do assinante.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Credencial inicial atende a politica minima de provisionamento.',
        ];
    }

    private function checklistItem(string $code, string $label, string $status, string $message): array
    {
        return compact('code', 'label', 'status', 'message');
    }

    private function conflictItem(string $code, string $message, string $subscriberCode): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'subscriberCode' => $subscriberCode,
        ];
    }

    private function detectRetryStep(array $result): string
    {
        $steps = is_array($result['steps'] ?? null) ? $result['steps'] : [];
        foreach ($steps as $step) {
            if (($step['status'] ?? '') === 'failed' && isset(self::PROVISION_STEPS[(string) ($step['code'] ?? '')])) {
                return (string) $step['code'];
            }
        }

        return 'prepare_env';
    }
}
