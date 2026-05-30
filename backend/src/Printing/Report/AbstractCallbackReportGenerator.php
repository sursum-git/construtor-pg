<?php

declare(strict_types=1);

namespace App\Printing\Report;

use App\Printing\Contract\ReportGeneratorInterface;
use App\Printing\DTO\ReportRequest;
use App\Printing\DTO\ReportResult;

abstract class AbstractCallbackReportGenerator implements ReportGeneratorInterface
{
    /**
     * @param \Closure(ReportRequest): string $builder
     */
    public function __construct(
        private readonly \Closure $builder,
    ) {
    }

    public function generate(ReportRequest $request): ReportResult
    {
        return new ReportResult(
            $request->format,
            $request->reportId,
            $this->contentType(),
            ($this->builder)($request),
            'download',
            ['layer' => 'artifact_generation']
        );
    }

    abstract protected function contentType(): string;
}
