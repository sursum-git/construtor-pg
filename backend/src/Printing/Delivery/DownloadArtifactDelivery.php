<?php

declare(strict_types=1);

namespace App\Printing\Delivery;

use App\Printing\DTO\PrintResult;
use App\Printing\DTO\ReportResult;

final class DownloadArtifactDelivery
{
    /**
     * @return array<string, mixed>
     */
    public function deliverReport(ReportResult $result): array
    {
        return [
            'ok' => true,
            'format' => $result->format,
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'contentBase64' => base64_encode($result->content),
            'deliveryMode' => $result->deliveryMode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deliverPrint(PrintResult $result): array
    {
        return [
            'ok' => $result->status->value !== 'failed',
            'format' => $result->format,
            'fileName' => $result->fileName,
            'contentType' => $result->contentType,
            'contentBase64' => base64_encode($result->content),
            'deliveryMode' => $result->deliveryMode,
            'status' => $result->status->value,
        ];
    }
}
