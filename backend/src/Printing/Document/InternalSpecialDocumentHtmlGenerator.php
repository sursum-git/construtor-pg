<?php

declare(strict_types=1);

namespace App\Printing\Document;

use App\Printing\Enum\ContentType;

final class InternalSpecialDocumentHtmlGenerator extends AbstractCallbackDocumentArtifactGenerator
{
    protected function contentType(): string
    {
        return ContentType::Html->value;
    }
}
