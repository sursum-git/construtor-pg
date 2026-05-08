<?php

namespace App\Runtime;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class RuntimeJobRegistry
{
    /**
     * @param iterable<RuntimeJobHandlerInterface> $handlers
     */
    public function __construct(
        #[TaggedIterator('app.runtime_job_handler')]
        private readonly iterable $handlers,
    ) {
    }

    public function has(string $jobType): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($jobType)) {
                return true;
            }
        }

        return false;
    }

    public function get(string $jobType): RuntimeJobHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($jobType)) {
                return $handler;
            }
        }

        throw new RuntimeHttpException('RUNTIME_JOB_HANDLER_NOT_FOUND', 'Handler de job runtime nao encontrado.', 500, [
            'jobType' => $jobType,
        ]);
    }
}
