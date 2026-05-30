<?php

declare(strict_types=1);

namespace App\Printing\Enum;

enum PrinterLanguage: string
{
    case Pdf = 'pdf';
    case Html = 'html';
    case Raw = 'raw';
    case EscPos = 'escpos';
    case Zpl = 'zpl';
    case Epl = 'epl';
}
