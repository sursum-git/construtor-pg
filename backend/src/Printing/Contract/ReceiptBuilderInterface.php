<?php

declare(strict_types=1);

namespace App\Printing\Contract;

use App\Printing\DTO\PrintJob;
use App\Printing\DTO\PrinterConfig;

interface ReceiptBuilderInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function build(array $data, PrinterConfig $printer): PrintJob;
}
