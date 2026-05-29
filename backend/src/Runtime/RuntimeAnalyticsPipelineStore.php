<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class RuntimeAnalyticsPipelineStore
{
    public const PIPELINE_VERSION_TABLE = 'runtime_analytics_pipeline_version';
    public const EXECUTION_TABLE = 'runtime_analytics_pipeline_execution';
    public const EXECUTION_STEP_TABLE = 'runtime_analytics_pipeline_execution_step';
    public const PUBLISHED_DATASET_TABLE = 'runtime_analytics_published_dataset';
    public const PUBLISHED_DATASET_VERSION_TABLE = 'runtime_analytics_published_dataset_version';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function storageReady(): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist([
                self::PIPELINE_VERSION_TABLE,
                self::EXECUTION_TABLE,
                self::EXECUTION_STEP_TABLE,
                self::PUBLISHED_DATASET_TABLE,
                self::PUBLISHED_DATASET_VERSION_TABLE,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function ensurePipelineVersion(string $tenantId, string $screenId, string $pipelineId, array $definition): array
    {
        $this->assertReady();

        $hash = hash('sha256', (string) json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PIPELINE_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('pipeline_id = :pipelineId')
            ->andWhere('definition_hash = :hash')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('pipelineId', $pipelineId)
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (is_array($existing)) {
            return $this->normalizePipelineVersionRow($existing);
        }

        $versionNo = (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(MAX(version_no), 0)')
            ->from(self::PIPELINE_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('pipeline_id = :pipelineId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('pipelineId', $pipelineId)
            ->executeQuery()
            ->fetchOne() + 1;

        $now = new \DateTimeImmutable();
        $this->connection->insert(self::PIPELINE_VERSION_TABLE, [
            'tenant_id' => $tenantId,
            'screen_id' => $screenId,
            'pipeline_id' => $pipelineId,
            'version_no' => $versionNo,
            'definition_hash' => $hash,
            'definition_json' => $definition,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'definition_json' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ]);

        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PIPELINE_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('pipeline_id = :pipelineId')
            ->andWhere('version_no = :versionNo')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('pipelineId', $pipelineId)
            ->setParameter('versionNo', $versionNo)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $this->normalizePipelineVersionRow(is_array($row) ? $row : []);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function createExecution(array $row): array
    {
        $this->assertReady();
        $now = new \DateTimeImmutable();
        $executionId = trim((string) ($row['executionId'] ?? '')) ?: 'apx-' . bin2hex(random_bytes(6));
        $record = [
            'tenant_id' => (string) ($row['tenantId'] ?? 'default'),
            'screen_id' => (string) ($row['screenId'] ?? ''),
            'pipeline_id' => (string) ($row['pipelineId'] ?? ''),
            'pipeline_version_id' => (int) ($row['pipelineVersionId'] ?? 0),
            'execution_code' => $executionId,
            'mode' => (string) ($row['mode'] ?? 'run'),
            'status' => (string) ($row['status'] ?? 'pending'),
            'working_dataset_json' => $row['workingDataset'] ?? null,
            'metadata_json' => $row['metadata'] ?? null,
            'row_count' => (int) ($row['rowCount'] ?? 0),
            'error_message' => $row['errorMessage'] ?? null,
            'started_at' => $row['startedAt'] ?? $now,
            'finished_at' => $row['finishedAt'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->connection->insert(self::EXECUTION_TABLE, $record, [
            'working_dataset_json' => Types::JSON,
            'metadata_json' => Types::JSON,
            'started_at' => Types::DATETIME_IMMUTABLE,
            'finished_at' => Types::DATETIME_IMMUTABLE,
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ]);

        return $this->findExecution($executionId) ?? [];
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function updateExecution(string $executionId, array $patch): ?array
    {
        $this->assertReady();
        $existing = $this->findExecution($executionId);
        if ($existing === null) {
            return null;
        }
        $data = [];
        $types = [];
        $map = [
            'status' => 'status',
            'mode' => 'mode',
            'rowCount' => 'row_count',
            'errorMessage' => 'error_message',
        ];
        foreach ($map as $source => $column) {
            if (array_key_exists($source, $patch)) {
                $data[$column] = $patch[$source];
            }
        }
        if (array_key_exists('workingDataset', $patch)) {
            $data['working_dataset_json'] = $patch['workingDataset'];
            $types['working_dataset_json'] = Types::JSON;
        }
        if (array_key_exists('metadata', $patch)) {
            $data['metadata_json'] = $patch['metadata'];
            $types['metadata_json'] = Types::JSON;
        }
        if (array_key_exists('startedAt', $patch)) {
            $data['started_at'] = $patch['startedAt'];
            $types['started_at'] = Types::DATETIME_IMMUTABLE;
        }
        if (array_key_exists('finishedAt', $patch)) {
            $data['finished_at'] = $patch['finishedAt'];
            $types['finished_at'] = Types::DATETIME_IMMUTABLE;
        }
        $data['updated_at'] = new \DateTimeImmutable();
        $types['updated_at'] = Types::DATETIME_IMMUTABLE;

        $this->connection->update(self::EXECUTION_TABLE, $data, ['execution_code' => $executionId], $types);

        return $this->findExecution($executionId);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function appendStepExecution(array $row): void
    {
        $this->assertReady();
        $now = new \DateTimeImmutable();
        $this->connection->insert(self::EXECUTION_STEP_TABLE, [
            'execution_code' => (string) ($row['executionId'] ?? ''),
            'step_id' => (string) ($row['stepId'] ?? ''),
            'step_type' => (string) ($row['stepType'] ?? ''),
            'position' => (int) ($row['position'] ?? 0),
            'status' => (string) ($row['status'] ?? 'succeeded'),
            'row_count' => (int) ($row['rowCount'] ?? 0),
            'output_columns_json' => $row['outputColumns'] ?? null,
            'metadata_json' => $row['metadata'] ?? null,
            'error_message' => $row['errorMessage'] ?? null,
            'started_at' => $row['startedAt'] ?? $now,
            'finished_at' => $row['finishedAt'] ?? $now,
            'created_at' => $now,
        ], [
            'output_columns_json' => Types::JSON,
            'metadata_json' => Types::JSON,
            'started_at' => Types::DATETIME_IMMUTABLE,
            'finished_at' => Types::DATETIME_IMMUTABLE,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function publishDatasetVersion(array $payload): array
    {
        $this->assertReady();
        $tenantId = (string) ($payload['tenantId'] ?? 'default');
        $screenId = (string) ($payload['screenId'] ?? '');
        $pipelineId = (string) ($payload['pipelineId'] ?? '');
        $datasetId = (string) ($payload['publishedDatasetId'] ?? '');
        $executionId = (string) ($payload['executionId'] ?? '');
        $versionNo = (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(MAX(version_no), 0)')
            ->from(self::PUBLISHED_DATASET_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('dataset_id = :datasetId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('datasetId', $datasetId)
            ->executeQuery()
            ->fetchOne() + 1;
        $datasetRow = $this->ensurePublishedDataset($tenantId, $screenId, $datasetId, $pipelineId);
        $now = new \DateTimeImmutable();
        $schema = $payload['schema'] ?? [];
        $data = $payload['data'] ?? [];
        $metadata = $payload['metadata'] ?? [];

        $this->connection->update(
            self::PUBLISHED_DATASET_VERSION_TABLE,
            ['status' => 'superseded', 'superseded_at' => $now],
            ['tenant_id' => $tenantId, 'screen_id' => $screenId, 'dataset_id' => $datasetId, 'status' => 'published'],
            ['superseded_at' => Types::DATETIME_IMMUTABLE]
        );

        $this->connection->insert(self::PUBLISHED_DATASET_VERSION_TABLE, [
            'published_dataset_id' => (int) ($datasetRow['id'] ?? 0),
            'tenant_id' => $tenantId,
            'screen_id' => $screenId,
            'pipeline_id' => $pipelineId,
            'dataset_id' => $datasetId,
            'version_no' => $versionNo,
            'status' => 'published',
            'execution_code' => $executionId,
            'schema_json' => $schema,
            'data_json' => $data,
            'row_count' => count(is_array($data['rows'] ?? null) ? $data['rows'] : []),
            'fingerprint' => hash('sha256', (string) json_encode([$schema, $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'metadata_json' => $metadata,
            'published_at' => $now,
            'superseded_at' => null,
            'rolled_back_from_version_no' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'schema_json' => Types::JSON,
            'data_json' => Types::JSON,
            'metadata_json' => Types::JSON,
            'published_at' => Types::DATETIME_IMMUTABLE,
            'superseded_at' => Types::DATETIME_IMMUTABLE,
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ]);

        $version = $this->findPublishedDatasetVersion($tenantId, $screenId, $datasetId, $versionNo);
        $this->connection->update(self::PUBLISHED_DATASET_TABLE, [
            'active_version_no' => (int) ($version['versionNo'] ?? $versionNo),
            'updated_at' => $now,
        ], ['id' => (int) ($datasetRow['id'] ?? 0)], ['updated_at' => Types::DATETIME_IMMUTABLE]);

        return $version ?? [];
    }

    public function rollbackPublishedDatasetVersion(string $tenantId, string $screenId, string $datasetId, int $versionNo): ?array
    {
        $this->assertReady();
        $target = $this->findPublishedDatasetVersion($tenantId, $screenId, $datasetId, $versionNo);
        if ($target === null) {
            return null;
        }
        $now = new \DateTimeImmutable();
        $this->connection->update(
            self::PUBLISHED_DATASET_VERSION_TABLE,
            ['status' => 'superseded', 'superseded_at' => $now],
            ['tenant_id' => $tenantId, 'screen_id' => $screenId, 'dataset_id' => $datasetId, 'status' => 'published'],
            ['superseded_at' => Types::DATETIME_IMMUTABLE]
        );
        $this->connection->update(
            self::PUBLISHED_DATASET_VERSION_TABLE,
            ['status' => 'rolled_back', 'superseded_at' => null, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'screen_id' => $screenId, 'dataset_id' => $datasetId, 'version_no' => $versionNo],
            ['updated_at' => Types::DATETIME_IMMUTABLE]
        );
        $datasetRow = $this->findPublishedDataset($tenantId, $screenId, $datasetId);
        if ($datasetRow) {
            $this->connection->update(self::PUBLISHED_DATASET_TABLE, [
                'active_version_no' => $versionNo,
                'updated_at' => $now,
            ], ['id' => (int) $datasetRow['id']], ['updated_at' => Types::DATETIME_IMMUTABLE]);
        }

        return $this->findPublishedDatasetVersion($tenantId, $screenId, $datasetId, $versionNo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublishedDatasetVersions(string $tenantId, string $screenId, string $datasetId): array
    {
        $this->assertReady();
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PUBLISHED_DATASET_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('dataset_id = :datasetId')
            ->orderBy('version_no', 'DESC')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('datasetId', $datasetId)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn (array $row): array => $this->normalizePublishedVersionRow($row), $rows);
    }

    public function activePublishedDatasetVersion(string $tenantId, string $screenId, string $datasetId): ?array
    {
        $this->assertReady();
        $dataset = $this->findPublishedDataset($tenantId, $screenId, $datasetId);
        if (!$dataset || (int) ($dataset['activeVersionNo'] ?? 0) <= 0) {
            return null;
        }

        return $this->findPublishedDatasetVersion($tenantId, $screenId, $datasetId, (int) $dataset['activeVersionNo']);
    }

    public function findExecution(string $executionId): ?array
    {
        $this->assertReady();
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::EXECUTION_TABLE)
            ->where('execution_code = :executionId')
            ->setParameter('executionId', $executionId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->normalizeExecutionRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExecutions(string $tenantId, string $screenId, string $pipelineId, int $limit = 50): array
    {
        $this->assertReady();
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::EXECUTION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('pipeline_id = :pipelineId')
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(max(1, min(300, $limit)))
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('pipelineId', $pipelineId)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn (array $row): array => $this->normalizeExecutionRow($row), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExecutionSteps(string $executionId): array
    {
        $this->assertReady();
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::EXECUTION_STEP_TABLE)
            ->where('execution_code = :executionId')
            ->orderBy('position', 'ASC')
            ->setParameter('executionId', $executionId)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'executionId' => (string) ($row['execution_code'] ?? ''),
                'stepId' => (string) ($row['step_id'] ?? ''),
                'stepType' => (string) ($row['step_type'] ?? ''),
                'position' => (int) ($row['position'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'rowCount' => (int) ($row['row_count'] ?? 0),
                'outputColumns' => $this->decodeJson($row['output_columns_json'] ?? null),
                'metadata' => $this->decodeJson($row['metadata_json'] ?? null),
                'errorMessage' => $row['error_message'] ?? null,
                'startedAt' => $this->formatDateTime($row['started_at'] ?? null),
                'finishedAt' => $this->formatDateTime($row['finished_at'] ?? null),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminRows(?string $tenantId = null): array
    {
        $this->assertReady();
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::EXECUTION_TABLE)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(300);
        if ($tenantId !== null && $tenantId !== '') {
            $qb->where('tenant_id = :tenantId')->setParameter('tenantId', $tenantId);
        }

        return array_map(fn (array $row): array => $this->normalizeExecutionRow($row), $qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return array<string, mixed>
     */
    private function ensurePublishedDataset(string $tenantId, string $screenId, string $datasetId, string $pipelineId): array
    {
        $existing = $this->findPublishedDataset($tenantId, $screenId, $datasetId);
        if ($existing !== null) {
            return $existing;
        }
        $now = new \DateTimeImmutable();
        $this->connection->insert(self::PUBLISHED_DATASET_TABLE, [
            'tenant_id' => $tenantId,
            'screen_id' => $screenId,
            'pipeline_id' => $pipelineId,
            'dataset_id' => $datasetId,
            'active_version_no' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ]);

        return $this->findPublishedDataset($tenantId, $screenId, $datasetId) ?? [];
    }

    private function findPublishedDataset(string $tenantId, string $screenId, string $datasetId): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PUBLISHED_DATASET_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('dataset_id = :datasetId')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('datasetId', $datasetId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'pipelineId' => (string) ($row['pipeline_id'] ?? ''),
            'datasetId' => (string) ($row['dataset_id'] ?? ''),
            'activeVersionNo' => $row['active_version_no'] !== null ? (int) $row['active_version_no'] : null,
            'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            'updatedAt' => $this->formatDateTime($row['updated_at'] ?? null),
        ];
    }

    private function findPublishedDatasetVersion(string $tenantId, string $screenId, string $datasetId, int $versionNo): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::PUBLISHED_DATASET_VERSION_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('dataset_id = :datasetId')
            ->andWhere('version_no = :versionNo')
            ->setParameter('tenantId', $tenantId)
            ->setParameter('screenId', $screenId)
            ->setParameter('datasetId', $datasetId)
            ->setParameter('versionNo', $versionNo)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->normalizePublishedVersionRow($row) : null;
    }

    private function assertReady(): void
    {
        if (!$this->storageReady()) {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_STORAGE_NOT_READY', 'Storage de pipeline analytics nao encontrado. Execute as migrations.', 500);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizePipelineVersionRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'pipelineId' => (string) ($row['pipeline_id'] ?? ''),
            'versionNo' => (int) ($row['version_no'] ?? 0),
            'definitionHash' => (string) ($row['definition_hash'] ?? ''),
            'definition' => $this->decodeJson($row['definition_json'] ?? null),
            'status' => (string) ($row['status'] ?? ''),
            'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            'updatedAt' => $this->formatDateTime($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeExecutionRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'pipelineId' => (string) ($row['pipeline_id'] ?? ''),
            'pipelineVersionId' => (int) ($row['pipeline_version_id'] ?? 0),
            'executionId' => (string) ($row['execution_code'] ?? ''),
            'mode' => (string) ($row['mode'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'workingDataset' => $this->decodeJson($row['working_dataset_json'] ?? null),
            'metadata' => $this->decodeJson($row['metadata_json'] ?? null),
            'rowCount' => (int) ($row['row_count'] ?? 0),
            'errorMessage' => $row['error_message'] ?? null,
            'startedAt' => $this->formatDateTime($row['started_at'] ?? null),
            'finishedAt' => $this->formatDateTime($row['finished_at'] ?? null),
            'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            'updatedAt' => $this->formatDateTime($row['updated_at'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizePublishedVersionRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'publishedDatasetIdRef' => (int) ($row['published_dataset_id'] ?? 0),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'pipelineId' => (string) ($row['pipeline_id'] ?? ''),
            'datasetId' => (string) ($row['dataset_id'] ?? ''),
            'versionNo' => (int) ($row['version_no'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'executionId' => (string) ($row['execution_code'] ?? ''),
            'schema' => $this->decodeJson($row['schema_json'] ?? null),
            'data' => $this->decodeJson($row['data_json'] ?? null),
            'rowCount' => (int) ($row['row_count'] ?? 0),
            'fingerprint' => (string) ($row['fingerprint'] ?? ''),
            'metadata' => $this->decodeJson($row['metadata_json'] ?? null),
            'publishedAt' => $this->formatDateTime($row['published_at'] ?? null),
            'supersededAt' => $this->formatDateTime($row['superseded_at'] ?? null),
            'rolledBackFromVersionNo' => $row['rolled_back_from_version_no'] !== null ? (int) $row['rolled_back_from_version_no'] : null,
            'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            'updatedAt' => $this->formatDateTime($row['updated_at'] ?? null),
        ];
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }

        return json_decode($value, true);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->format(DATE_ATOM);
        }

        return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
    }
}
