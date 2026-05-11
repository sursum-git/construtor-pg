<?php

namespace App\Runtime\CustomCode;

final class ProdutoPdmCodeGenerator
{
    public static function generate(array $context): string
    {
        $properties = is_array($context['properties'] ?? null) ? $context['properties'] : [];
        $sequence = is_array($context['sequence'] ?? null) ? $context['sequence'] : [];
        $familia = self::segment((string) ($properties['familia'] ?? 'GERAL'));
        $grupo = self::segment((string) ($properties['grupo'] ?? 'PADRAO'));
        $linha = self::segment((string) ($properties['linha'] ?? 'ITEM'));
        $suffix = (string) ($sequence['padded'] ?? '0001');

        return implode('-', [$familia, $grupo, $linha, $suffix]);
    }

    private static function segment(string $value): string
    {
        $text = strtoupper(trim($value));
        $text = preg_replace('/[^A-Z0-9]+/', '-', $text) ?: '';
        return trim($text, '-') ?: 'ITEM';
    }
}
