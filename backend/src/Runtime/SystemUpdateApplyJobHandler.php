<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;
use Doctrine\ORM\EntityManagerInterface;

class SystemUpdateApplyJobHandler implements RuntimeJobHandlerInterface
{
    public function __construct(
        private readonly SystemUpdateService $updates,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'system.update.apply';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $version = trim((string) ($payload['releaseVersion'] ?? ''));
        $executionId = (int) ($payload['executionId'] ?? 0);
        if ($version === '') {
            throw new \RuntimeException('Release da atualizacao nao informada no job.');
        }

        $job->setResult([
            'phase' => 'running',
            'message' => 'Atualizacao em execucao.',
            'releaseVersion' => $version,
        ]);
        $this->entityManager->flush();

        $result = $this->updates->applyRelease(
            $version,
            $executionId > 0 ? $executionId : null,
            ($payload['forceConsent'] ?? false) === true,
            trim((string) ($payload['mode'] ?? 'manual')) ?: 'manual',
            trim((string) ($payload['source'] ?? 'job')) ?: 'job',
            trim((string) ($payload['targetSubscriberCode'] ?? '')) ?: null
        );

        return [
            'phase' => 'completed',
            'message' => (string) (($result['status'] ?? '') === 'succeeded' ? 'Atualizacao aplicada.' : 'Atualizacao finalizada com falha.'),
            'releaseVersion' => $version,
            'status' => (string) ($result['status'] ?? 'failed'),
        ];
    }
}
