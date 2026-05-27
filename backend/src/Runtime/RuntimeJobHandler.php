<?php

namespace App\Runtime;

use App\Repository\RuntimeAsyncJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RuntimeJobHandler
{
    public function __construct(
        private readonly RuntimeAsyncJobRepository $jobs,
        private readonly RuntimeJobRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeEventService $events,
    ) {
    }

    public function __invoke(RuntimeJobMessage $message): void
    {
        $job = $this->jobs->find($message->getJobId());
        if (!$job) {
            throw new \RuntimeException('Job runtime nao encontrado: ' . $message->getJobId());
        }

        if ($job->getStatus() === 'succeeded') {
            return;
        }

        $job->markRunning();
        $this->entityManager->flush();

        try {
            $result = $this->registry->get($job->getJobType())->handle($job);
            $job->markSucceeded($this->sanitizeValue($result));
            $this->entityManager->flush();
            $this->publishJobEvent('runtime.job.completed', $job, $this->sanitizeValue($result));
        } catch (\Throwable $error) {
            $result = $job->getResult();
            if (!is_array($result)) {
                $result = [];
            }
            $result['exception'] = $error::class;
            $result['message'] = $error->getMessage();
            $job->markFailed($error->getMessage(), $this->sanitizeValue($result));
            $this->entityManager->flush();
            $this->publishJobEvent('runtime.job.failed', $job, $this->sanitizeValue($result));
            throw $error;
        }
    }

    private function publishJobEvent(string $eventCode, \App\Entity\RuntimeAsyncJob $job, array $result): void
    {
        try {
            $this->events->publish($eventCode, [
                'tenantId' => $job->getTenantId(),
                'userId' => $job->getUserId(),
                'screenId' => $job->getScreenId(),
                'programCode' => $job->getProgramId(),
                'entityCode' => $job->getEntityCode(),
                'recordId' => $job->getRecordId(),
                'operation' => $job->getJobType(),
                'before' => [],
                'after' => [
                    'jobId' => $job->getId(),
                    'jobType' => $job->getJobType(),
                    'status' => $job->getStatus(),
                    'attempts' => $job->getAttempts(),
                    'result' => $result,
                ],
                'changes' => [],
                'transactionId' => $job->getTransaction()?->getId(),
                'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ], [
                'source' => 'runtime.job',
                'tenantId' => $job->getTenantId(),
                'userId' => $job->getUserId(),
                'sessionId' => $job->getSessionId(),
                'screenId' => $job->getScreenId(),
                'programCode' => $job->getProgramId(),
                'entityCode' => $job->getEntityCode(),
                'recordId' => $job->getRecordId(),
                'operation' => $job->getJobType(),
            ]);
        } catch (\Throwable) {
            // O evento do job nao deve esconder o resultado real do job.
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (!is_array($value)) {
            return null;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->sanitizeValue($item);
        }

        return $result;
    }
}
