<?php

namespace App\Runtime;

final class RuntimeJobMessage
{
    public function __construct(
        private readonly int $jobId,
    ) {
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }
}
