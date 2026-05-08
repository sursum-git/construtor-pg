<?php

namespace App\Runtime;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.runtime_business_rule')]
interface RuntimeBusinessRuleHandlerInterface
{
    public function supports(string $entityCode, string $actionId): bool;

    public function beforeValidate(RuntimeBusinessRuleContext $context): void;

    public function beforePersist(RuntimeBusinessRuleContext $context): void;

    public function afterPersist(RuntimeBusinessRuleContext $context): void;

    public function afterCommit(RuntimeBusinessRuleContext $context): void;
}
