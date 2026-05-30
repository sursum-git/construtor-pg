<?php

declare(strict_types=1);

namespace App\Printing\Report;

use App\Printing\Enum\ContentType;

final class InternalReportXlsxGenerator extends AbstractCallbackReportGenerator
{
    protected function contentType(): string
    {
        return ContentType::Xlsx->value;
    }
}
