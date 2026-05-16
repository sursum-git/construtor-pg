<?php

namespace App\ImportExport;

use App\Runtime\RuntimeHttpException;

final class ImportExportXmlRenderer
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
        $document = new \DOMDocument('1.0', $encoding);
        $document->formatOutput = ($destination['prettyPrint'] ?? true) !== false;

        $namespaces = $this->normalizeNamespaces(is_array($destination['namespaces'] ?? null) ? $destination['namespaces'] : []);
        $sourceMap = $this->buildSourceMap($sources);
        $rootName = $this->safeXmlName((string) ($destination['rootName'] ?? 'items'), 'items');
        $root = $this->createElement($document, $rootName, $namespaces);
        $document->appendChild($root);
        $this->applyAttributes($document, $root, is_array($destination['rootAttributes'] ?? null) ? $destination['rootAttributes'] : [], [], $namespaces);

        $layouts = is_array($destination['xmlLayouts'] ?? null) ? $destination['xmlLayouts'] : [];
        if ($layouts) {
            $limit = $preview ? max(1, (int) ($mapping['options']['previewLimit'] ?? 20)) : PHP_INT_MAX;
            $state = ['count' => 0, 'limit' => $limit];
            $this->renderLayouts($document, $root, $layouts, $sourceMap, null, $namespaces, $state);
        } else {
            $this->renderFlatItems($document, $root, $destination, $sources, $preview, $namespaces, $mapping);
        }

        $xml = $document->saveXML();
        if (!is_string($xml) || $xml === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_RENDER_FAILED', 'Nao foi possivel gerar o XML.', 500);
        }

        return [
            'fileName' => $this->valueMapper->resolveFileName($destination['fileNamePattern'] ?? 'export.xml', 'xml'),
            'mimeType' => 'application/xml; charset=' . $encoding,
            'content' => $this->encodingHelper->encodeOutput($xml, $encoding),
            'previewText' => $xml,
        ];
    }

    private function renderFlatItems(\DOMDocument $document, \DOMElement $root, array $destination, array $sources, bool $preview, array $namespaces, array $mapping): void
    {
        if (count($sources) !== 1) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_MULTI_SOURCE_NOT_SUPPORTED', 'XML nesta etapa aceita apenas uma fonte por mapeamento.', 422);
        }

        $columns = is_array($destination['columns'] ?? null) ? $destination['columns'] : [];
        $columns = array_values(array_filter(array_map(function ($column) {
            if (!is_array($column)) {
                return null;
            }
            $sourcePath = trim((string) ($column['sourcePath'] ?? ''));
            $targetName = trim((string) ($column['targetName'] ?? $column['name'] ?? $sourcePath));
            if ($sourcePath === '' || $targetName === '') {
                return null;
            }
            return [
                'sourcePath' => $sourcePath,
                'targetName' => $targetName,
                'transforms' => is_array($column['transforms'] ?? null) ? $column['transforms'] : [],
            ];
        }, $columns)));
        if (!$columns) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_COLUMNS_REQUIRED', 'Informe as colunas do XML.', 422);
        }

        $itemName = $this->safeXmlName((string) ($destination['itemName'] ?? 'item'), 'item');
        $records = is_array($sources[0]['records'] ?? null) ? $sources[0]['records'] : [];
        if ($preview) {
            $records = array_slice($records, 0, max(1, (int) ($mapping['options']['previewLimit'] ?? 20)));
        }

        foreach ($records as $record) {
            $itemNode = $this->createElement($document, $itemName, $namespaces);
            foreach ($columns as $column) {
                $value = $this->valueMapper->extractValue($record, $column['sourcePath']);
                $value = $this->valueMapper->applyTransforms($value, $column['transforms']);
                $fieldNode = $this->createElement(
                    $document,
                    $this->safeXmlName($column['targetName'], 'field'),
                    $namespaces,
                    $this->valueMapper->stringifyValue($value)
                );
                $itemNode->appendChild($fieldNode);
            }
            $root->appendChild($itemNode);
        }
    }

    private function renderLayouts(
        \DOMDocument $document,
        \DOMElement $parentNode,
        array $layouts,
        array $sourceMap,
        ?array $parentRecord,
        array $namespaces,
        array &$state
    ): void {
        foreach ($layouts as $layout) {
            if ($state['count'] >= $state['limit']) {
                return;
            }
            if (!is_array($layout)) {
                continue;
            }
            $records = $this->resolveLayoutRecords($layout, $sourceMap, $parentRecord);
            foreach ($records as $record) {
                if ($state['count'] >= $state['limit']) {
                    return;
                }
                $name = $this->safeXmlName((string) ($layout['name'] ?? 'item'), 'item');
                $node = $this->createElement($document, $name, $namespaces);
                $this->applyAttributes($document, $node, is_array($layout['attributes'] ?? null) ? $layout['attributes'] : [], $record, $namespaces);
                $this->appendFieldElements($document, $node, is_array($layout['fields'] ?? null) ? $layout['fields'] : [], $record, $namespaces);
                $this->appendNodeText($document, $node, $layout, $record);
                $parentNode->appendChild($node);
                $state['count']++;
                $children = is_array($layout['children'] ?? null) ? $layout['children'] : [];
                if ($children) {
                    $this->renderLayouts($document, $node, $children, $sourceMap, $record, $namespaces, $state);
                }
            }
        }
    }

    private function resolveLayoutRecords(array $layout, array $sourceMap, ?array $parentRecord): array
    {
        $sourceAlias = trim((string) ($layout['sourceAlias'] ?? ''));
        if ($sourceAlias === '') {
            return [$parentRecord ?? []];
        }
        $source = $sourceMap[$sourceAlias] ?? null;
        if (!$source) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_SOURCE_NOT_FOUND', 'Leiaute XML referencia uma fonte inexistente.', 422, [
                'sourceAlias' => $sourceAlias,
            ]);
        }
        $records = is_array($source['records'] ?? null) ? $source['records'] : [];
        $linkBy = is_array($layout['linkBy'] ?? null) ? $layout['linkBy'] : [];
        if (!$linkBy) {
            return $records;
        }
        if ($parentRecord === null) {
            return [];
        }

        return array_values(array_filter($records, fn (array $record): bool => $this->matchLinkBy($record, $parentRecord, $linkBy)));
    }

    private function matchLinkBy(array $record, array $parentRecord, array $linkBy): bool
    {
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

    private function applyAttributes(
        \DOMDocument $document,
        \DOMElement $node,
        array $attributes,
        array $record,
        array $namespaces
    ): void {
        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            $name = trim((string) ($attribute['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $this->resolveLayoutValue($attribute, $record);
            $this->setAttribute($document, $node, $name, $value, $namespaces);
        }
    }

    private function appendFieldElements(
        \DOMDocument $document,
        \DOMElement $node,
        array $fields,
        array $record,
        array $namespaces
    ): void {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? $field['targetName'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = $this->resolveLayoutValue($field, $record);
            $child = $this->createElement($document, $name, $namespaces, $value);
            $node->appendChild($child);
        }
    }

    private function appendNodeText(\DOMDocument $document, \DOMElement $node, array $layout, array $record): void
    {
        $hasConstant = array_key_exists('textConstant', $layout);
        $sourcePath = trim((string) ($layout['textSourcePath'] ?? ''));
        if (!$hasConstant && $sourcePath === '') {
            return;
        }
        $value = $hasConstant
            ? $layout['textConstant']
            : $this->valueMapper->extractValue($record, $sourcePath);
        $value = $this->valueMapper->applyTransforms($value, is_array($layout['textTransforms'] ?? null) ? $layout['textTransforms'] : []);
        $text = $document->createTextNode($this->valueMapper->stringifyValue($value));
        $node->appendChild($text);
    }

    private function resolveLayoutValue(array $config, array $record): string
    {
        $value = array_key_exists('constant', $config)
            ? $config['constant']
            : $this->valueMapper->extractValue($record, (string) ($config['sourcePath'] ?? ''));
        $value = $this->valueMapper->applyTransforms($value, is_array($config['transforms'] ?? null) ? $config['transforms'] : []);

        return $this->valueMapper->stringifyValue($value);
    }

    private function createElement(\DOMDocument $document, string $name, array $namespaces, ?string $value = null): \DOMElement
    {
        [$qualifiedName, $uri] = $this->resolveName($name, $namespaces);
        if ($uri !== null) {
            return $document->createElementNS($uri, $qualifiedName, $value ?? '');
        }

        return $document->createElement($qualifiedName, $value ?? '');
    }

    private function setAttribute(\DOMDocument $document, \DOMElement $node, string $name, string $value, array $namespaces): void
    {
        [$qualifiedName, $uri] = $this->resolveName($name, $namespaces);
        if ($uri !== null) {
            $node->setAttributeNS($uri, $qualifiedName, $value);

            return;
        }
        $node->setAttribute($qualifiedName, $value);
    }

    private function resolveName(string $value, array $namespaces): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return ['node', null];
        }
        if (!str_contains($trimmed, ':')) {
            return [$this->safeXmlName($trimmed, 'node'), null];
        }
        [$prefix, $local] = explode(':', $trimmed, 2);
        $prefix = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($prefix)) ?: '';
        $local = $this->safeXmlName($local, 'node');
        if ($prefix === '' || !isset($namespaces[$prefix])) {
            return [$local, null];
        }

        return [$prefix . ':' . $local, $namespaces[$prefix]];
    }

    private function normalizeNamespaces(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $prefix = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim((string) ($item['prefix'] ?? ''))) ?: '';
            $uri = trim((string) ($item['uri'] ?? ''));
            if ($prefix === '' || $uri === '') {
                continue;
            }
            $result[$prefix] = $uri;
        }

        return $result;
    }

    private function buildSourceMap(array $sources): array
    {
        $sourceMap = [];
        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source['alias'])) {
                continue;
            }
            $sourceMap[(string) $source['alias']] = $source;
        }

        return $sourceMap;
    }

    private function safeXmlName(string $value, string $fallback): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', trim($value)) ?: '';
        if ($normalized === '' || preg_match('/^[0-9.-]/', $normalized)) {
            return $fallback;
        }

        return $normalized;
    }
}
