<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;

class ClienteProcessamentoJobHandler implements RuntimeJobHandlerInterface
{
    public function supports(string $jobType): bool
    {
        return $jobType === 'clientes.processamento';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];

        return [
            'resultType' => (string) ($parameters['resultado'] ?? 'grid'),
            'status' => 'succeeded',
        ];
    }
}
