<?php

declare(strict_types=1);

namespace App\Printing\DTO;

final readonly class ReportRequest
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $reportId,
        public string $title,
        public string $format,
        public array $context = [],
    ) {
    }
}
