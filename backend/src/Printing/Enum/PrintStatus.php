<?php

declare(strict_types=1);

namespace App\Printing\Enum;

enum PrintStatus: string
{
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Archived = 'archived';
    case Failed = 'failed';
    case NotImplemented = 'not_implemented';
}
