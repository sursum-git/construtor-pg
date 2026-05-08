<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class RuntimeAsyncJobService
{
    /**
     * @var array<int, array{type: string, payload: array<string, mixed>, options: array<string, mixed>}>
     */
    private array $pending = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeExecutionContext $executionContext,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeJobRegistry $registry,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function schedule(string $type, array $payload, array $options = []): void
    {
        if (!$this->registry->has($type)) {
            throw new RuntimeHttpException('RUNTIME_JOB_TYPE_NOT_ALLOWED', 'Tipo de job runtime nao permitido.', 500, [
                'jobType' => $type,
            ]);
        }

        $this->pending[] = [
            'type' => $type,
            'payload' => $this->redactSensitiveValues($payload),
            'options' => $options,
        ];
    }

    /**
     * @return list<array{id: int|null, type: string, status: string, message?: string}>
     */
    public function flushPending(): array
    {
        if (!$this->pending) {
            return [];
        }

        $queued = [];
        foreach ($this->pending as $item) {
            $job = $this->createJob($item['type'], $item['payload'], $item['options']);
            $this->entityManager->persist($job);
            $this->entityManager->flush();

            try {
                $this->messageBus->dispatch(new RuntimeJobMessage((int) $job->getId()));
                $this->transactions->log('job.queued', 'Job assincrono enfileirado.', metadata: [
                    'jobId' => $job->getId(),
                    'jobType' => $job->getJobType(),
                    'entityCode' => $job->getEntityCode(),
                    'recordId' => $job->getRecordId(),
                ]);
                $summary = [
                    'id' => $job->getId(),
                    'type' => $job->getJobType(),
                    'status' => $job->getStatus(),
                ];
                if ($message = $this->summaryMessage($item['options'])) {
                    $summary['message'] = $message;
                }
                $queued[] = $summary;
            } catch (\Throwable $error) {
                $job->markFailed($error->getMessage(), [
                    'phase' => 'dispatch',
                    'exception' => $error::class,
                ]);
                $this->transactions->log('job.queue_failed', 'Falha ao enfileirar job assincrono.', metadata: [
                    'jobId' => $job->getId(),
                    'jobType' => $job->getJobType(),
                    'exception' => $error::class,
                ]);
                $summary = [
                    'id' => $job->getId(),
                    'type' => $job->getJobType(),
                    'status' => $job->getStatus(),
                ];
                if ($message = $this->summaryMessage($item['options'])) {
                    $summary['message'] = $message;
                }
                $queued[] = $summary;
            }
        }

        $this->pending = [];
        $this->entityManager->flush();

        return $queued;
    }

    public function clearPending(): void
    {
        $this->pending = [];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function createJob(string $type, array $payload, array $options): RuntimeAsyncJob
    {
        $context = $this->executionContext->all();
        $user = $this->permissions->getCurrentUserPayload();

        return (new RuntimeAsyncJob())
            ->setTransaction($this->transactions->getCurrent())
            ->setTenantId($this->permissions->getTenantId())
            ->setUserId($this->permissions->getUserId())
            ->setUserName($user['name'] ?? null)
            ->setSessionId($this->permissions->getSessionId())
            ->setScreenId((string) ($options['screenId'] ?? $context['screenId'] ?? ''))
            ->setProgramId((string) ($options['programId'] ?? $context['programId'] ?? ''))
            ->setEntityCode($options['entityCode'] ?? $context['entityCode'] ?? null)
            ->setRecordId($options['recordId'] ?? $context['recordId'] ?? null)
            ->setActionId($options['actionId'] ?? $context['actionId'] ?? null)
            ->setJobType($type)
            ->setStatus('queued')
            ->setPayload($payload)
            ->setResult([]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function summaryMessage(array $options): ?string
    {
        $message = trim((string) ($options['message'] ?? ''));

        return $message !== '' ? $message : null;
    }

    private function redactSensitiveValues(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_scalar($value) || $value === null ? $value : null;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }
            $result[$key] = $this->redactSensitiveValues($item);
        }

        return $result;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        return (bool) preg_match('/(senha|password|token|authorization|api[_-]?key|secret|private[_-]?key)/i', $key);
    }
}
