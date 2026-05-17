<?php

namespace App\Provisioning;

use App\Entity\AuthSubscriber;
use App\Entity\RuntimeAsyncJob;
use App\Repository\AuthSubscriberRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAsyncJobService;
use App\Runtime\CentralControlResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberProvisioningService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthSubscriberRepository $subscribers,
        private readonly RuntimeAsyncJobRepository $jobs,
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
        return [
            'centralControl' => $this->central->resolve(),
            'environment' => $this->environmentIdentity->resolve(),
            'subscribers' => $this->central->isCentralControl() ? $this->listSubscribers() : [],
            'jobs' => $this->central->isCentralControl() ? $this->listProvisionJobs() : [],
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
        $metadata['provisioning'] = [
            'instanceCode' => $this->normalizeOptional($payload['instanceCode'] ?? ''),
            'databaseEnvironment' => $this->normalizeOptional($payload['databaseEnvironment'] ?? ''),
            'databaseIdentity' => $this->normalizeOptional($payload['databaseIdentity'] ?? ''),
            'databaseName' => $this->normalizeOptional($payload['databaseName'] ?? ''),
            'adminUsername' => $this->normalizeOptional($payload['adminUsername'] ?? ''),
            'adminDisplayName' => $this->normalizeOptional($payload['adminDisplayName'] ?? ''),
            'adminEmail' => $this->normalizeOptional($payload['adminEmail'] ?? ''),
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
        $instanceCode = $this->normalizeOptional($payload['instanceCode'] ?? $provisioning['instanceCode'] ?? '') ?: ('construtor-pg-' . $subscriber->getCode());
        $databaseEnvironment = $this->normalizeOptional($payload['databaseEnvironment'] ?? $provisioning['databaseEnvironment'] ?? '') ?: 'prod';
        $databaseIdentity = $this->normalizeOptional($payload['databaseIdentity'] ?? $provisioning['databaseIdentity'] ?? '') ?: ('saas:' . $subscriber->getCode());
        $databaseName = $this->normalizeOptional($payload['databaseName'] ?? $provisioning['databaseName'] ?? '') ?: ('construtor_pg_' . str_replace('-', '_', strtolower($subscriber->getCode())));
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
        $package = $this->packages->build([
            'subscriberCode' => $subscriber->getCode(),
            'subscriberName' => $subscriber->getName(),
            'subscriberDocument' => $subscriber->getDocument(),
            'databaseEnvironment' => $this->normalizeOptional($payload['databaseEnvironment'] ?? $provisioning['databaseEnvironment'] ?? '') ?: 'prod',
            'databaseIdentity' => $this->normalizeOptional($payload['databaseIdentity'] ?? $provisioning['databaseIdentity'] ?? '') ?: ('onprem:' . $subscriber->getCode()),
            'databaseName' => $this->normalizeOptional($payload['databaseName'] ?? $provisioning['databaseName'] ?? '') ?: ('construtor_pg_' . str_replace('-', '_', strtolower($subscriber->getCode()))),
            'adminUsername' => $this->normalizeOptional($payload['adminUsername'] ?? $provisioning['adminUsername'] ?? '') ?: 'admin',
            'adminDisplayName' => $this->normalizeOptional($payload['adminDisplayName'] ?? $provisioning['adminDisplayName'] ?? '') ?: ('Administrador ' . $subscriber->getName()),
            'instanceCode' => $this->normalizeOptional($payload['instanceCode'] ?? $provisioning['instanceCode'] ?? '') ?: ('construtor-pg-' . $subscriber->getCode()),
            'generatedBy' => $this->permissions->getUserId(),
        ]);

        return $package;
    }

    private function formatSubscriber(AuthSubscriber $subscriber): array
    {
        $metadata = $subscriber->getMetadata();
        $provisioning = is_array($metadata['provisioning'] ?? null) ? $metadata['provisioning'] : [];

        return [
            'id' => $subscriber->getId(),
            'code' => $subscriber->getCode(),
            'name' => $subscriber->getName(),
            'document' => $subscriber->getDocument(),
            'principal' => $subscriber->isPrincipal(),
            'enabled' => $subscriber->isEnabled(),
            'metadata' => $metadata,
            'instanceCode' => (string) ($provisioning['instanceCode'] ?? ''),
            'databaseEnvironment' => (string) ($provisioning['databaseEnvironment'] ?? ''),
            'databaseIdentity' => (string) ($provisioning['databaseIdentity'] ?? ''),
            'databaseName' => (string) ($provisioning['databaseName'] ?? ''),
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
}
