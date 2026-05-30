<?php

declare(strict_types=1);

namespace App\Printing\Enum;

enum ContentType: string
{
    case Pdf = 'application/pdf';
    case Csv = 'text/csv; charset=utf-8';
    case Xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case Html = 'text/html; charset=utf-8';
    case Raw = 'application/octet-stream';
}
