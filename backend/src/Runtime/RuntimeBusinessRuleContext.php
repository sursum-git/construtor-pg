<?php

namespace App\Runtime;

class RuntimeBusinessRuleContext
{
    public const DEFAULT_VALIDATION_MESSAGE = 'Existem inconsistencias no formulario.';

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

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function messageItem(
        ?string $field,
        string $messageKey,
        array $params = [],
        string $type = 'error',
        ?string $message = null,
    ): array {
        return [
            'field' => $field,
            'type' => $type,
            'messageKey' => trim($messageKey),
            'messageParams' => $params,
            'message' => $message,
        ];
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $effects
     * @param array<string, mixed> $titleParams
     * @param array<string, mixed> $details
     */
    public function throwValidation(
        string $errorCode,
        array $messages,
        array $effects = [],
        string $titleKey = 'validation.title.inconsistencies',
        array $titleParams = [],
        ?string $title = 'Inconsistencias encontradas',
        string $message = self::DEFAULT_VALIDATION_MESSAGE,
        int $statusCode = 422,
        array $details = [],
        string $severity = 'error',
    ): never {
        throw new RuntimeValidationException(
            $errorCode,
            $message,
            [
                'status' => $severity === 'warning' ? 'warning' : 'blocked',
                'title' => $title,
                'titleKey' => $titleKey,
                'titleParams' => $titleParams,
                'messages' => $messages,
            ],
            $effects,
            $statusCode,
            $details,
            $severity,
        );
    }
}
