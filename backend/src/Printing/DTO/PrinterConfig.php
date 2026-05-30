<?php

declare(strict_types=1);

namespace App\Printing\DTO;

use App\Printing\Enum\PrinterLanguage;

final readonly class PrinterConfig
{
    public function __construct(
        public string $transport,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $queue = null,
        public ?PrinterLanguage $language = null,
    ) {
    }
}
