<?php

declare(strict_types=1);

namespace App\Printing\Contract;

use App\Printing\DTO\DocumentArtifactRequest;
use App\Printing\DTO\PrintResult;

interface DocumentArtifactGeneratorInterface
{
    public function generate(DocumentArtifactRequest $request): PrintResult;
}
