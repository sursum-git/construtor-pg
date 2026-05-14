<?php

namespace App\ImportExport;

use App\Entity\BuilderEntity;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeHttpException;

final class ImportExportDestinationWriter
{
    public function __construct(
        private readonly ImportExportSourceLoader $sourceLoader,
        private readonly ImportExportValueMapper $valueMapper,
        private readonly RuntimeEntityActionService $runtimeEntities,
        private readonly RuntimeApiEntityActionService $runtimeApis,
    ) {
    }

    public function executeDestinationOperation(string $entityCode, string $operation, array $record): array
    {
        $entity = $this->sourceLoader->findEntity($entityCode);

        return match ($this->sourceLoader->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', $operation, [
                'entityCode' => $entityCode,
                'operation' => $operation,
            ], ['entityCode' => $entityCode, 'record' => $record] + $record),
            'api' => $this->runtimeApis->handle('admin.import-export', $operation, [
                'entityCode' => $entityCode,
                'operation' => $operation,
            ], ['entityCode' => $entityCode, 'record' => $record] + $record),
            'odoo' => throw new RuntimeHttpException('IMPORT_EXPORT_ODOO_WRITE_NOT_SUPPORTED', 'Destino Odoo ainda nao suporta gravacao por mapeamento.', 422, [
                'entityCode' => $entityCode,
            ]),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade de destino nao suportado nesta etapa.', 422),
        };
    }

    public function resolveDestinationAction(array $destination, array $mappedRecord): array
    {
        $operation = strtolower(trim((string) ($destination['operation'] ?? 'create')));
        if ($operation === 'upsert') {
            $matchBy = is_array($destination['matchBy'] ?? null) ? $destination['matchBy'] : [];
            if (!$matchBy) {
                throw new RuntimeHttpException('IMPORT_EXPORT_MATCH_BY_REQUIRED', 'Operacao upsert exige matchBy.', 422);
            }
            $existing = $this->findDestinationRecordByMatch($destination['entityCode'], $mappedRecord, $matchBy);
            if ($existing) {
                $record = $mappedRecord;
                $primaryKey = $this->sourceLoader->findEntityPrimaryKey($destination['entityCode']);
                $record[$primaryKey] = $existing[$primaryKey] ?? $existing['id'] ?? null;

                return ['operation' => 'update', 'record' => $record];
            }

            return ['operation' => 'create', 'record' => $mappedRecord];
        }

        return ['operation' => $operation, 'record' => $mappedRecord];
    }

    private function findDestinationRecordByMatch(string $entityCode, array $mappedRecord, array $matchBy): ?array
    {
        $filters = [];
        foreach ($matchBy as $item) {
            if (!is_array($item)) {
                continue;
            }
            $targetField = trim((string) ($item['targetField'] ?? ''));
            $sourcePath = trim((string) ($item['sourcePath'] ?? $targetField));
            if ($targetField === '') {
                continue;
            }
            $value = $this->valueMapper->extractValue($mappedRecord, $sourcePath);
            $filters[] = [
                'field' => $targetField,
                'operator' => 'eq',
                'value' => $value,
            ];
        }
        if (!$filters) {
            return null;
        }
        $entity = $this->sourceLoader->findEntity($entityCode);
        $records = $this->sourceLoader->readEntityRecords($entity, [
            'take' => 1,
            'skip' => 0,
            'filter' => [
                'logic' => 'and',
                'filters' => $filters,
            ],
        ]);

        return $records[0] ?? null;
    }
}
