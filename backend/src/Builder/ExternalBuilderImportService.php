<?php

namespace App\Builder;

class ExternalBuilderImportService
{
    public function __construct(
        private readonly ProgramBuilderService $builder,
    ) {
    }

    public function validate(array $payload): array
    {
        return $this->builder->validateExternalDraft($payload);
    }
}
