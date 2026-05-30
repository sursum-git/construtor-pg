<?php

declare(strict_types=1);

namespace App\Printing\Transport;

use App\Printing\Contract\PrinterTransportInterface;
use App\Printing\DTO\PrintJob;
use App\Printing\DTO\PrintResult;
use App\Printing\DTO\PrinterConfig;
use App\Printing\Exception\PrintingException;

final class NullPrinterTransport implements PrinterTransportInterface
{
    public function send(PrintJob $job, PrinterConfig $printer): PrintResult
    {
        throw new PrintingException('Transporte fisico de impressao ainda nao implementado para a bridge interna.');
    }
}
