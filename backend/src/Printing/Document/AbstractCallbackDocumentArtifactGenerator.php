<?php

declare(strict_types=1);

namespace App\Printing\Document;

use App\Printing\Contract\DocumentArtifactGeneratorInterface;
use App\Printing\DTO\DocumentArtifactRequest;
use App\Printing\DTO\PrintResult;
use App\Printing\Enum\PrintStatus;

abstract class AbstractCallbackDocumentArtifactGenerator implements DocumentArtifactGeneratorInterface
{
    /**
     * @param \Closure(DocumentArtifactRequest): string $builder
     */
    public function __construct(
        private readonly \Closure $builder,
    ) {
    }

    public function generate(DocumentArtifactRequest $request): PrintResult
    {
        return new PrintResult(
            PrintStatus::Ready,
            $request->format,
            $request->documentId,
            $this->contentType(),
            ($this->builder)($request),
            'download',
            ['layer' => 'artifact_generation']
        );
    }

    abstract protected function contentType(): string;
}
