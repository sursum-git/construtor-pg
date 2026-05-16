<?php

namespace App\ImportExport;

use App\Entity\BuilderEntity;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeOdooEntityActionService;

final class ImportExportSourceLoader
{
    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly RuntimeEntityActionService $runtimeEntities,
        private readonly RuntimeApiEntityActionService $runtimeApis,
        private readonly RuntimeOdooEntityActionService $runtimeOdoo,
        private readonly ImportExportValueMapper $valueMapper,
    ) {
    }

    public function loadSources(array $mapping, array $parameters, bool $preview): array
    {
        $sources = is_array($mapping['sources'] ?? null) && $mapping['sources']
            ? $mapping['sources']
            : (isset($mapping['source']) ? [$mapping['source']] : []);
        if (!$sources) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_REQUIRED', 'Informe pelo menos uma fonte no mapeamento.', 422);
        }
        $loaded = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $loaded[] = $this->loadSource($source, $parameters, $preview);
        }

        return $loaded;
    }

    public function findEntity(string $entityCode): BuilderEntity
    {
        $entity = $this->entities->findOneBy(['code' => trim($entityCode)]);
        if (!$entity instanceof BuilderEntity) {
            throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_NOT_FOUND', 'Entidade nao encontrada para o mapeamento.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        return $entity;
    }

    public function entityRuntimeType(BuilderEntity $entity): string
    {
        if ($entity->getEntityType() !== 'api') {
            return 'persistence';
        }
        $apiSource = is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : [];
        if (($apiSource['providerType'] ?? '') === 'odoo') {
            return 'odoo';
        }

        return 'api';
    }

    public function findEntityPrimaryKey(string $entityCode): string
    {
        $entity = $this->findEntity($entityCode);
        foreach ($entity->getFields() as $field) {
            if ($field->isPrimaryKey()) {
                return $field->getCode();
            }
        }

        return 'id';
    }

    public function readEntityRecords(BuilderEntity $entity, array $payload): array
    {
        $response = match ($this->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            'api' => $this->runtimeApis->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            'odoo' => $this->runtimeOdoo->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade nao suportado nesta etapa.', 422, [
                'entityCode' => $entity->getCode(),
                'entityType' => $entity->getEntityType(),
            ]),
        };

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function getEntityRecord(BuilderEntity $entity, mixed $recordId): array
    {
        return match ($this->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            'api' => $this->runtimeApis->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            'odoo' => $this->runtimeOdoo->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade nao suportado nesta etapa.', 422),
        };
    }

    private function loadSource(array $source, array $parameters, bool $preview): array
    {
        $type = strtolower(trim((string) ($source['type'] ?? 'entity')));
        if ($type === 'file') {
            return $this->loadFileSource($source, $parameters, $preview);
        }
        if ($type !== 'entity') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_TYPE_NOT_SUPPORTED', 'Fonte suportada nesta etapa: entity ou file/xml.', 422);
        }
        $entityCode = trim((string) ($source['entityCode'] ?? ''));
        $alias = trim((string) ($source['alias'] ?? $entityCode));
        if ($entityCode === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_ENTITY_REQUIRED', 'Fonte precisa informar entityCode.', 422);
        }
        $entity = $this->findEntity($entityCode);
        $mode = strtolower(trim((string) ($source['mode'] ?? 'list')));
        $limit = max(1, min(500, (int) ($source['limit'] ?? ($preview ? 20 : 200))));
        if ($mode === 'single') {
            $recordId = $source['recordId'] ?? $parameters[$alias . '_id'] ?? $parameters['recordId'] ?? null;
            if ($recordId === null || $recordId === '') {
                $read = $this->readEntityRecords($entity, [
                    'take' => 1,
                    'skip' => 0,
                ]);
                $record = $read[0] ?? null;
                $records = $record ? [$record] : [];
            } else {
                $records = [$this->getEntityRecord($entity, $recordId)];
            }
        } else {
            $records = $this->readEntityRecords($entity, [
                'take' => $limit,
                'skip' => 0,
                'filter' => $source['filter'] ?? null,
                'sort' => $source['sort'] ?? [],
            ]);
        }

        return [
            'alias' => $alias,
            'entityCode' => $entityCode,
            'entityType' => $entity->getEntityType(),
            'records' => $records,
        ];
    }

    private function loadFileSource(array $source, array $parameters, bool $preview): array
    {
        $fileFormat = strtolower(trim((string) ($source['fileFormat'] ?? 'xml')));
        if ($fileFormat !== 'xml') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_FILE_FORMAT_NOT_SUPPORTED', 'Fonte file suporta apenas XML nesta etapa.', 422);
        }
        $alias = trim((string) ($source['alias'] ?? 'xml_file'));
        $contentParameter = trim((string) ($source['contentParameter'] ?? 'xmlContent'));
        $content = (string) ($source['content'] ?? $parameters[$contentParameter] ?? '');
        if (trim($content) === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_CONTENT_REQUIRED', 'Informe o conteudo XML no parametro configurado.', 422, [
                'contentParameter' => $contentParameter,
            ]);
        }
        $recordPath = trim((string) ($source['recordPath'] ?? ''));
        if ($recordPath === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_RECORD_PATH_REQUIRED', 'Fonte XML exige recordPath.', 422);
        }
        $fields = is_array($source['fields'] ?? null) ? $source['fields'] : [];
        if (!$fields) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_SOURCE_FIELDS_REQUIRED', 'Fonte XML exige fields.', 422);
        }

        $document = new \DOMDocument();
        $loaded = @$document->loadXML($content);
        if ($loaded !== true) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_INVALID', 'Conteudo XML invalido.', 422);
        }

        $xpath = new \DOMXPath($document);
        foreach (is_array($source['namespaces'] ?? null) ? $source['namespaces'] : [] as $namespace) {
            if (!is_array($namespace)) {
                continue;
            }
            $prefix = trim((string) ($namespace['prefix'] ?? ''));
            $uri = trim((string) ($namespace['uri'] ?? ''));
            if ($prefix !== '' && $uri !== '') {
                $xpath->registerNamespace($prefix, $uri);
            }
        }

        $nodes = $xpath->query($recordPath);
        if (!$nodes instanceof \DOMNodeList) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_PATH_INVALID', 'Nao foi possivel consultar recordPath no XML.', 422, [
                'recordPath' => $recordPath,
            ]);
        }

        $records = [];
        $limit = max(1, min(500, (int) ($source['limit'] ?? ($preview ? 20 : 200))));
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNode) {
                continue;
            }
            $record = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $targetField = trim((string) ($field['targetField'] ?? $field['name'] ?? ''));
                $query = trim((string) ($field['xpath'] ?? $field['sourcePath'] ?? ''));
                if ($targetField === '' || $query === '') {
                    continue;
                }
                $value = $this->readXmlValue($xpath, $node, $query);
                $record[$targetField] = $this->valueMapper->applyTransforms($value, is_array($field['transforms'] ?? null) ? $field['transforms'] : []);
            }
            $records[] = $record;
            if (count($records) >= $limit) {
                break;
            }
        }

        return [
            'alias' => $alias,
            'entityCode' => null,
            'entityType' => 'file',
            'records' => $records,
        ];
    }

    private function readXmlValue(\DOMXPath $xpath, \DOMNode $contextNode, string $query): mixed
    {
        $result = $xpath->evaluate($query, $contextNode);
        if ($result instanceof \DOMNodeList) {
            if ($result->length === 0) {
                return null;
            }
            if ($result->length === 1) {
                return $result->item(0)?->textContent;
            }
            $values = [];
            foreach ($result as $node) {
                $values[] = $node instanceof \DOMNode ? $node->textContent : null;
            }
            return $values;
        }

        return $result;
    }
}
