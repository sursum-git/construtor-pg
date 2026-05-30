<?php

declare(strict_types=1);

namespace App\Printing\DTO;

final readonly class ReportResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $format,
        public string $fileName,
        public string $contentType,
        public string $content,
        public string $deliveryMode = 'download',
        public array $metadata = [],
    ) {
    }
}
