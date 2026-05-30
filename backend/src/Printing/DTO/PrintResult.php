<?php

declare(strict_types=1);

namespace App\Printing\DTO;

use App\Printing\Enum\PrintStatus;

final readonly class PrintResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public PrintStatus $status,
        public string $format,
        public string $fileName,
        public string $contentType,
        public string $content,
        public string $deliveryMode = 'download',
        public array $metadata = [],
    ) {
    }
}
