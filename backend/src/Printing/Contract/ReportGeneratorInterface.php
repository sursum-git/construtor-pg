<?php

declare(strict_types=1);

namespace App\Printing\Contract;

use App\Printing\DTO\ReportRequest;
use App\Printing\DTO\ReportResult;

interface ReportGeneratorInterface
{
    public function generate(ReportRequest $request): ReportResult;
}
