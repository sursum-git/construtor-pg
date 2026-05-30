<?php

declare(strict_types=1);

namespace App\Printing\Delivery;

use App\Printing\DTO\PrintResult;
use App\Printing\DTO\ReportResult;

final class QzTrayArtifactDelivery
{
    /**
     * @param array<string, mixed> $printer
     * @return array<string, mixed>
     */
    public function deliverReport(ReportResult $result, array $printer): array
    {
        return [
            'format' => $result->format,
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'contentBase64' => base64_encode($result->content),
            'deliveryMode' => 'qz_tray',
            'printer' => $this->normalizePrinter($printer),
        ];
    }

    /**
     * @param array<string, mixed> $printer
     * @return array<string, mixed>
     */
    public function deliverPrint(PrintResult $result, array $printer): array
    {
        return [
            'ok' => $result->status->value === 'ready',
            'format' => $result->format,
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'contentBase64' => base64_encode($result->content),
            'deliveryMode' => 'qz_tray',
            'printer' => $this->normalizePrinter($printer),
        ];
    }

    /**
     * @param array<string, mixed> $printer
     * @return array<string, mixed>
     */
    private function normalizePrinter(array $printer): array
    {
        $copies = max(1, (int) ($printer['copies'] ?? 1));

        return [
            'transport' => 'qz_tray',
            'printerName' => trim((string) ($printer['printerName'] ?? '')),
            'jobName' => trim((string) ($printer['jobName'] ?? '')),
            'copies' => $copies,
        ];
    }
}
