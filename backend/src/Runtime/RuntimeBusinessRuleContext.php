<?php

namespace App\Runtime;

class RuntimeBusinessRuleContext
{
    /** @var callable|null */
    private $logger = null;

    public function __construct(
        private readonly array $definition,
        private readonly string $operation,
        private readonly string $actionId,
        private readonly array $payload,
        private array $values,
        private array $before = [],
        private array $after = [],
    ) {
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function getEntityCode(): string
    {
        return (string) $this->definition['entityCode'];
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getActionId(): string
    {
        return $this->actionId;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    public function getBefore(): array
    {
        return $this->before;
    }

    public function setBefore(array $before): void
    {
        $this->before = $before;
    }

    public function getAfter(): array
    {
        return $this->after;
    }

    public function setAfter(array $after): void
    {
        $this->after = $after;
    }

    public function setLogger(?callable $logger): void
    {
        $this->logger = $logger;
    }

    public function log(
        string $eventType,
        ?string $message = null,
        array $metadata = [],
        array $before = [],
        array $after = [],
    ): void {
        if (!is_callable($this->logger)) {
            return;
        }

        ($this->logger)($eventType, $message, $before, $after, $metadata);
    }
}
