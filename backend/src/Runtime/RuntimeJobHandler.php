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
            $job->markSucceeded($this->sanitizeValue($result));
            $this->entityManager->flush();
        } catch (\Throwable $error) {
            $result = $job->getResult();
            if (!is_array($result)) {
                $result = [];
            }
            $result['exception'] = $error::class;
            $result['message'] = $error->getMessage();
            $job->markFailed($error->getMessage(), $this->sanitizeValue($result));
            $this->entityManager->flush();
            throw $error;
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
