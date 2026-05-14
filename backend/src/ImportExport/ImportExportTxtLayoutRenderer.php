<?php

namespace App\ImportExport;

use App\Runtime\RuntimeHttpException;

final class ImportExportTxtLayoutRenderer
{
    public function __construct(
        private readonly ImportExportValueMapper $valueMapper,
        private readonly ImportExportEncodingHelper $encodingHelper,
    ) {
    }

    public function build(array $mapping, array $sources, bool $preview): array
    {
        $destination = $mapping['destination'];
        $encoding = $this->encodingHelper->normalizeEncodingLabel((string) ($destination['encodingLabel'] ?? 'UTF-8'));
        $recordLayouts = is_array($destination['recordLayouts'] ?? null) ? $destination['recordLayouts'] : [];
        $recordLayouts = $this->applyLayoutDefaults($recordLayouts, $encoding);
        if (!$recordLayouts) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_LAYOUT_REQUIRED', 'Informe os leiautes do TXT.', 422);
        }

        $sourceMap = [];
        foreach ($sources as $source) {
            $sourceMap[$source['alias']] = $source;
        }

        $lines = [];
        $limit = $preview ? max(1, (int) ($mapping['options']['previewLimit'] ?? 20)) : PHP_INT_MAX;
        $this->renderNodes($recordLayouts, $sourceMap, null, $lines, $limit);

        $lineBreak = (string) ($destination['lineBreak'] ?? "\r\n");
        $contentUtf8 = implode($lineBreak, $lines) . $lineBreak;
        $content = $this->encodingHelper->encodeOutput($contentUtf8, $encoding);

        return [
            'fileName' => $this->valueMapper->resolveFileName($destination['fileNamePattern'] ?? 'export.txt', 'txt'),
            'mimeType' => 'text/plain; charset=' . $encoding,
            'content' => $content,
            'previewText' => $contentUtf8,
        ];
    }

    private function applyLayoutDefaults(array $layouts, string $encoding): array
    {
        $normalized = [];
        foreach ($layouts as $layout) {
            if (!is_array($layout)) {
                continue;
            }
            $layout['encodingLabel'] = trim((string) ($layout['encodingLabel'] ?? '')) !== ''
                ? (string) $layout['encodingLabel']
                : $encoding;
            $layout['children'] = $this->applyLayoutDefaults(is_array($layout['children'] ?? null) ? $layout['children'] : [], $encoding);
            $normalized[] = $layout;
        }

        return $normalized;
    }

    private function renderNodes(array $layouts, array $sourceMap, ?array $parentRecord, array &$lines, int $limit): void
    {
        foreach ($layouts as $layout) {
            if (count($lines) >= $limit) {
                return;
            }
            if (!is_array($layout)) {
                continue;
            }
            $this->renderNode($layout, $sourceMap, $parentRecord, $lines, $limit);
        }
    }

    private function renderNode(array $layout, array $sourceMap, ?array $parentRecord, array &$lines, int $limit): void
    {
        $nodeType = strtolower(trim((string) ($layout['nodeType'] ?? 'record')));
        $children = is_array($layout['children'] ?? null) ? $layout['children'] : [];

        if ($nodeType === 'group') {
            $this->renderNodes($children, $sourceMap, $parentRecord, $lines, $limit);

            return;
        }

        if ($nodeType === 'totalizer') {
            $records = $this->resolveNodeRecords($layout, $sourceMap, $parentRecord);
            $summaryRecord = $this->buildSummaryRecord($layout, $records, $parentRecord);
            if (!empty($layout['fields'])) {
                $lines[] = $this->renderLine($summaryRecord, $layout);
            }
            if (count($lines) >= $limit) {
                return;
            }
            if ($children) {
                $this->renderNodes($children, $sourceMap, $summaryRecord, $lines, $limit);
            }

            return;
        }

        $records = $this->resolveNodeRecords($layout, $sourceMap, $parentRecord);
        foreach ($records as $record) {
            if (count($lines) >= $limit) {
                return;
            }
            $decorated = $this->decorateRecord($record, $parentRecord);
            if (!empty($layout['fields'])) {
                $lines[] = $this->renderLine($decorated, $layout);
            }
            if (count($lines) >= $limit) {
                return;
            }
            if ($children) {
                $this->renderNodes($children, $sourceMap, $decorated, $lines, $limit);
            }
        }
    }

    private function resolveNodeRecords(array $layout, array $sourceMap, ?array $parentRecord): array
    {
        $alias = trim((string) ($layout['sourceAlias'] ?? $layout['sourceEntityCode'] ?? ''));
        if ($alias === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_SOURCE_NOT_FOUND', 'No de leiaute TXT precisa informar sourceAlias.', 422, [
                'recordType' => $layout['recordType'] ?? null,
                'nodeType' => $layout['nodeType'] ?? 'record',
            ]);
        }
        $source = $sourceMap[$alias] ?? null;
        if (!$source) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_SOURCE_NOT_FOUND', 'Leiaute TXT referencia uma fonte inexistente.', 422, [
                'sourceAlias' => $alias,
            ]);
        }

        $records = is_array($source['records'] ?? null) ? $source['records'] : [];
        $linkBy = is_array($layout['linkBy'] ?? null) ? $layout['linkBy'] : [];
        if (!$linkBy) {
            return $records;
        }

        return array_values(array_filter($records, fn (array $record): bool => $this->matchLinkBy($record, $parentRecord, $linkBy)));
    }

    private function matchLinkBy(array $record, ?array $parentRecord, array $linkBy): bool
    {
        if (!$parentRecord) {
            return false;
        }

        foreach ($linkBy as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $parentPath = trim((string) ($rule['parentPath'] ?? ''));
            $childField = trim((string) ($rule['childField'] ?? $rule['sourcePath'] ?? ''));
            if ($parentPath === '' || $childField === '') {
                continue;
            }
            $operator = strtolower(trim((string) ($rule['operator'] ?? 'eq')));
            $parentValue = $this->valueMapper->extractValue($parentRecord, $parentPath);
            $childValue = $this->valueMapper->extractValue($record, $childField);
            if (!$this->valueMapper->compareValues($childValue, $parentValue, $operator)) {
                return false;
            }
        }

        return true;
    }

    private function decorateRecord(array $record, ?array $parentRecord): array
    {
        if ($parentRecord === null) {
            return $record;
        }

        $record['_parent'] = $parentRecord;

        return $record;
    }

    private function buildSummaryRecord(array $layout, array $records, ?array $parentRecord): array
    {
        $aggregates = is_array($layout['aggregates'] ?? null) ? $layout['aggregates'] : [];
        $summary = [
            'count' => count($records),
        ];

        foreach ($aggregates as $aggregate) {
            if (!is_array($aggregate)) {
                continue;
            }
            $name = trim((string) ($aggregate['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = strtolower(trim((string) ($aggregate['type'] ?? 'count')));
            $sourcePath = trim((string) ($aggregate['sourcePath'] ?? ''));
            $summary[$name] = match ($type) {
                'count' => count($records),
                'sum' => $this->sumRecords($records, $sourcePath),
                default => null,
            };
        }

        return [
            '_summary' => $summary,
            '_parent' => $parentRecord,
        ];
    }

    private function sumRecords(array $records, string $sourcePath): float
    {
        if ($sourcePath === '') {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($records as $record) {
            $value = $this->valueMapper->extractValue($record, $sourcePath);
            if (is_numeric($value)) {
                $sum += (float) $value;
            }
        }

        return $sum;
    }

    private function renderLine(array $record, array $layout): string
    {
        $mode = strtolower(trim((string) ($layout['lineMode'] ?? 'fixed')));
        $fields = is_array($layout['fields'] ?? null) ? $layout['fields'] : [];
        if ($mode === 'delimited') {
            $separator = (string) ($layout['separator'] ?? ';');
            $items = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $value = array_key_exists('constant', $field)
                    ? $field['constant']
                    : $this->valueMapper->applyTransforms($this->valueMapper->extractValue($record, (string) ($field['sourcePath'] ?? '')), $field['transforms'] ?? []);
                $items[] = $this->valueMapper->stringifyValue($value);
            }

            return implode($separator, $items);
        }

        $cursor = 1;
        $widthMode = strtolower(trim((string) ($layout['widthMode'] ?? 'characters')));
        $encoding = $this->encodingHelper->normalizeEncodingLabel((string) ($layout['encodingLabel'] ?? 'UTF-8'));
        if ($widthMode === 'bytes') {
            return $this->renderFixedWidthByteLine($record, $fields, $encoding);
        }

        $line = '';
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $length = max(0, (int) ($field['length'] ?? 0));
            if ($length <= 0) {
                throw new RuntimeHttpException('IMPORT_EXPORT_TXT_FIELD_LENGTH_REQUIRED', 'Campo de leiaute posicional precisa informar length.', 422);
            }
            $start = max(1, (int) ($field['start'] ?? $cursor));
            $value = array_key_exists('constant', $field)
                ? $field['constant']
                : $this->valueMapper->applyTransforms($this->valueMapper->extractValue($record, (string) ($field['sourcePath'] ?? '')), $field['transforms'] ?? []);
            $text = $this->valueMapper->stringifyValue($value);
            $align = strtolower(trim((string) ($field['align'] ?? 'left')));
            $padChar = mb_substr((string) ($field['padChar'] ?? ' '), 0, 1) ?: ' ';
            $segment = $this->normalizeFixedWidthText($text, $length, $align, $padChar);
            $line = $this->applyFixedWidthCharacterSegment($line, $segment, $start, $length);
            $cursor = $start + $length;
        }

        return $line;
    }

    private function renderFixedWidthByteLine(array $record, array $fields, string $encoding): string
    {
        $cursor = 1;
        $binaryLine = '';
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $length = max(0, (int) ($field['length'] ?? 0));
            if ($length <= 0) {
                throw new RuntimeHttpException('IMPORT_EXPORT_TXT_FIELD_LENGTH_REQUIRED', 'Campo de leiaute posicional precisa informar length.', 422);
            }
            $start = max(1, (int) ($field['start'] ?? $cursor));
            $value = array_key_exists('constant', $field)
                ? $field['constant']
                : $this->valueMapper->applyTransforms($this->valueMapper->extractValue($record, (string) ($field['sourcePath'] ?? '')), $field['transforms'] ?? []);
            $text = $this->valueMapper->stringifyValue($value);
            $align = strtolower(trim((string) ($field['align'] ?? 'left')));
            $padChar = mb_substr((string) ($field['padChar'] ?? ' '), 0, 1) ?: ' ';
            $segment = $this->normalizeFixedWidthBytes($text, $length, $align, $padChar, $encoding);
            $binaryLine = $this->applyFixedWidthBinarySegment($binaryLine, $segment, $start, $length);
            $cursor = $start + $length;
        }

        return $this->decodeOutput($binaryLine, $encoding);
    }

    private function normalizeFixedWidthText(string $text, int $length, string $align, string $padChar): string
    {
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
        }
        $missing = $length - mb_strlen($text);
        if ($missing <= 0) {
            return $text;
        }

        $padding = str_repeat($padChar, $missing);

        return $align === 'right' ? $padding . $text : $text . $padding;
    }

    private function applyFixedWidthCharacterSegment(string $line, string $text, int $start, int $length): string
    {
        $lineLength = mb_strlen($line);
        if ($start > $lineLength + 1) {
            $line .= str_repeat(' ', $start - ($lineLength + 1));
        }

        $prefix = mb_substr($line, 0, max(0, $start - 1));
        $suffixStart = $start - 1 + $length;
        $suffix = $lineLength > $suffixStart ? mb_substr($line, $suffixStart) : '';

        return $prefix . $text . $suffix;
    }

    private function applyFixedWidthBinarySegment(string $binaryLine, string $segment, int $start, int $length): string
    {
        $lineLength = strlen($binaryLine);
        if ($start > $lineLength + 1) {
            $binaryLine .= str_repeat(' ', $start - ($lineLength + 1));
        }

        $prefix = substr($binaryLine, 0, max(0, $start - 1));
        $suffixStart = $start - 1 + $length;
        $suffix = $lineLength > $suffixStart ? substr($binaryLine, $suffixStart) : '';

        return $prefix . $segment . $suffix;
    }

    private function normalizeFixedWidthBytes(string $text, int $length, string $align, string $padChar, string $encoding): string
    {
        $encoded = $this->encodingHelper->encodeOutput($text, $encoding);
        if (strlen($encoded) > $length) {
            $encoded = $this->cutEncodedString($encoded, $length, $encoding);
        }
        $missing = $length - strlen($encoded);
        if ($missing <= 0) {
            return $encoded;
        }

        $encodedPad = $this->encodingHelper->encodeOutput($padChar, $encoding);
        if ($encodedPad === '') {
            $encodedPad = ' ';
        }
        $padding = substr(str_repeat($encodedPad, $missing + 2), 0, $missing);

        return $align === 'right' ? $padding . $encoded : $encoded . $padding;
    }

    private function cutEncodedString(string $encoded, int $length, string $encoding): string
    {
        $cut = mb_strcut($encoded, 0, $length, $encoding);
        if ($cut === false) {
            return substr($encoded, 0, $length);
        }

        return $cut;
    }

    private function decodeOutput(string $value, string $encoding): string
    {
        return $this->encodingHelper->decodeOutput($value, $encoding);
    }
}
