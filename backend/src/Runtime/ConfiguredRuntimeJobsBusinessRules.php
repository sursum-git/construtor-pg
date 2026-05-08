<?php

namespace App\Runtime;

class ConfiguredRuntimeJobsBusinessRules extends AbstractRuntimeBusinessRuleHandler
{
    public function __construct(
        private readonly RuntimeConfiguredJobScheduler $jobs,
    ) {
    }

    public function supports(string $entityCode, string $actionId): bool
    {
        return $entityCode !== '';
    }

    public function afterCommit(RuntimeBusinessRuleContext $context): void
    {
        $this->jobs->scheduleAfterSuccess($context);
    }
}
