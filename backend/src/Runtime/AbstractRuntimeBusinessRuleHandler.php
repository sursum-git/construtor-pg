<?php

namespace App\Runtime;

abstract class AbstractRuntimeBusinessRuleHandler implements RuntimeBusinessRuleHandlerInterface
{
    public function beforeValidate(RuntimeBusinessRuleContext $context): void
    {
    }

    public function beforePersist(RuntimeBusinessRuleContext $context): void
    {
    }

    public function afterPersist(RuntimeBusinessRuleContext $context): void
    {
    }

    public function afterCommit(RuntimeBusinessRuleContext $context): void
    {
    }
}
