<?php

namespace App\Runtime;

class RuntimeValidationException extends RuntimeHttpException
{
    public function __construct(
        string $errorCode,
        string $message,
        private readonly array $validation,
        private readonly array $effects = [],
        int $statusCode = 422,
        array $details = [],
        private readonly string $severity = 'error',
    ) {
        parent::__construct($errorCode, $message, $statusCode, $details);
    }

    public function getValidation(): array
    {
        return $this->validation;
    }

    public function getEffects(): array
    {
        return $this->effects;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }
}
