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
            $job->markSucceeded($this->sanitizeResult($result));
            $this->entityManager->flush();
        } catch (\Throwable $error) {
            $job->markFailed($error->getMessage(), [
                'exception' => $error::class,
            ]);
            $this->entityManager->flush();
            throw $error;
        }
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function sanitizeResult(array $result): array
    {
        return array_filter($result, static fn (mixed $value): bool => is_scalar($value) || $value === null);
    }
}
