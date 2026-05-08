<?php

namespace App\Runtime;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class RuntimeBusinessRuleRegistry
{
    /**
     * @param iterable<RuntimeBusinessRuleHandlerInterface> $handlers
     */
    public function __construct(
        #[TaggedIterator('app.runtime_business_rule')]
        private readonly iterable $handlers,
    ) {
    }

    public function beforeValidate(RuntimeBusinessRuleContext $context): void
    {
        $this->invoke('beforeValidate', $context);
    }

    public function beforePersist(RuntimeBusinessRuleContext $context): void
    {
        $this->invoke('beforePersist', $context);
    }

    public function afterPersist(RuntimeBusinessRuleContext $context): void
    {
        $this->invoke('afterPersist', $context);
    }

    public function afterCommit(RuntimeBusinessRuleContext $context): void
    {
        $this->invoke('afterCommit', $context);
    }

    private function invoke(string $method, RuntimeBusinessRuleContext $context): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($context->getEntityCode(), $context->getActionId())) {
                $handler->{$method}($context);
            }
        }
    }
}
