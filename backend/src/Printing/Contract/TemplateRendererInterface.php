<?php

declare(strict_types=1);

namespace App\Printing\Contract;

interface TemplateRendererInterface
{
    /**
     * @param array<string, scalar|null> $context
     */
    public function render(string $template, array $context): string;
}
