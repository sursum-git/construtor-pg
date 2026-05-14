<?php

namespace App\ImportExport;

final class ImportExportValueMapper
{
    public function mapRecord(array $record, array $fieldMappings): array
    {
        $mapped = [];
        foreach ($fieldMappings as $item) {
            if (!is_array($item)) {
                continue;
            }
            $targetPath = trim((string) ($item['targetPath'] ?? ''));
            if ($targetPath === '') {
                continue;
            }
            $value = array_key_exists('constant', $item)
                ? $item['constant']
                : $this->extractValue($record, (string) ($item['sourcePath'] ?? ''));
            $value = $this->applyTransforms($value, $item['transforms'] ?? []);
            $this->assignValue($mapped, $targetPath, $value);
        }

        return $mapped;
    }

    public function applyTransforms(mixed $value, mixed $transforms): mixed
    {
        if (!is_array($transforms)) {
            return $value;
        }
        foreach ($transforms as $transform) {
            if (is_string($transform)) {
                $transform = ['type' => $transform];
            }
            if (!is_array($transform)) {
                continue;
            }
            $type = strtolower(trim((string) ($transform['type'] ?? '')));
            $value = match ($type) {
                'trim' => is_string($value) ? trim($value) : $value,
                'upper' => is_string($value) ? mb_strtoupper($value) : $value,
                'lower' => is_string($value) ? mb_strtolower($value) : $value,
                'constant' => $transform['value'] ?? null,
                'concat' => $this->transformConcat($transform, $value),
                'date_format' => $this->transformDateFormat($value, (string) ($transform['format'] ?? 'Y-m-d')),
                'number_format' => $this->transformNumberFormat($value, $transform),
                'pad_left' => str_pad($this->stringifyValue($value), (int) ($transform['length'] ?? 0), (string) ($transform['padChar'] ?? '0'), STR_PAD_LEFT),
                'pad_right' => str_pad($this->stringifyValue($value), (int) ($transform['length'] ?? 0), (string) ($transform['padChar'] ?? ' '), STR_PAD_RIGHT),
                default => $value,
            };
        }

        return $value;
    }

    public function extractValue(array $record, string $path): mixed
    {
        $normalized = trim($path);
        if ($normalized === '' || $normalized === '$') {
            return $record;
        }
        $current = $record;
        foreach (explode('.', $normalized) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function escapeDelimited(string $value, string $quote): string
    {
        $escaped = str_replace($quote, $quote . $quote, $value);

        return $quote . $escaped . $quote;
    }

    public function resolveFileName(mixed $pattern, string $extension): string
    {
        $name = trim((string) $pattern);
        if ($name === '') {
            $name = 'export.' . $extension;
        }
        if (!str_contains($name, '.')) {
            $name .= '.' . $extension;
        }

        return $name;
    }

    public function compareValues(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            'eq' => $left == $right,
            'neq' => $left != $right,
            'contains' => str_contains(mb_strtolower($this->stringifyValue($left)), mb_strtolower($this->stringifyValue($right))),
            'startswith' => str_starts_with(mb_strtolower($this->stringifyValue($left)), mb_strtolower($this->stringifyValue($right))),
            'gt' => $left > $right,
            'gte' => $left >= $right,
            'lt' => $left < $right,
            'lte' => $left <= $right,
            default => $left == $right,
        };
    }

    private function transformConcat(array $transform, mixed $fallback): string
    {
        $parts = is_array($transform['parts'] ?? null) ? $transform['parts'] : [];
        if (!$parts) {
            return $this->stringifyValue($fallback);
        }
        $buffer = '';
        foreach ($parts as $part) {
            if (is_string($part)) {
                $buffer .= $part;
                continue;
            }
            if (!is_array($part)) {
                continue;
            }
            if (array_key_exists('constant', $part)) {
                $buffer .= $this->stringifyValue($part['constant']);
                continue;
            }
            $buffer .= $this->stringifyValue($part['value'] ?? '');
        }

        return $buffer;
    }

    private function transformDateFormat(mixed $value, string $format): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return (new \DateTimeImmutable((string) $value))->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function transformNumberFormat(mixed $value, array $transform): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        return number_format(
            (float) $value,
            max(0, (int) ($transform['decimals'] ?? 2)),
            (string) ($transform['decimalSeparator'] ?? ','),
            (string) ($transform['thousandSeparator'] ?? '.')
        );
    }

    private function assignValue(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', trim($path));
        $cursor = &$target;
        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;

                return;
            }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }
}
