<?php

declare(strict_types=1);

namespace App\Printing\Template;

use App\Printing\Contract\TemplateRendererInterface;
use App\Printing\Exception\InvalidTemplateException;

final class SimpleTemplateRenderer implements TemplateRendererInterface
{
    public function render(string $template, array $context): string
    {
        if (preg_match('/<\s*script|javascript\s*:/i', $template)) {
            throw new InvalidTemplateException('Template livre com script nao e aceito na bridge de impressao.');
        }

        return (string) preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', static function (array $matches) use ($context): string {
            $key = (string) ($matches[1] ?? '');
            $value = $context[$key] ?? '';
            if ($value === null) {
                return '';
            }

            return (string) $value;
        }, $template);
    }
}
