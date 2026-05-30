<?php

declare(strict_types=1);

namespace App\Printing\Report;

use App\Printing\Enum\ContentType;

final class InternalReportCsvGenerator extends AbstractCallbackReportGenerator
{
    protected function contentType(): string
    {
        return ContentType::Csv->value;
    }
}
