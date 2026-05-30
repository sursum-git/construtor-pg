<?php

declare(strict_types=1);

namespace App\Printing\DTO;

use App\Printing\Enum\PrinterLanguage;

final readonly class PrintJob
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $content,
        public string $contentType,
        public PrinterLanguage $language,
        public array $metadata = [],
    ) {
    }
}
