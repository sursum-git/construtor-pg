<?php

declare(strict_types=1);

namespace App\Printing\Contract;

use App\Printing\DTO\PrintJob;
use App\Printing\DTO\PrintResult;
use App\Printing\DTO\PrinterConfig;

interface PrinterTransportInterface
{
    public function send(PrintJob $job, PrinterConfig $printer): PrintResult;
}
