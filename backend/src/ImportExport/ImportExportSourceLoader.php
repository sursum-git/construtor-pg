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
        if (($source['type'] ?? 'entity') !== 'entity') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_TYPE_NOT_SUPPORTED', 'Fonte suportada nesta etapa: entity.', 422);
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
}
