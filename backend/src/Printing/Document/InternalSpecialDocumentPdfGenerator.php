<?php

declare(strict_types=1);

namespace App\Printing\Document;

use App\Printing\Enum\ContentType;

final class InternalSpecialDocumentPdfGenerator extends AbstractCallbackDocumentArtifactGenerator
{
    protected function contentType(): string
    {
        return ContentType::Pdf->value;
    }
}
