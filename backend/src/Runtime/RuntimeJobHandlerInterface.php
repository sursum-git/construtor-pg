<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.runtime_job_handler')]
interface RuntimeJobHandlerInterface
{
    public function supports(string $jobType): bool;

    /**
     * @return array<string, mixed>
     */
    public function handle(RuntimeAsyncJob $job): array;
}
