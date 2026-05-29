<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;

class RuntimeAnalyticsAuditStore
{
    private const TABLE = 'runtime_analytics_audit_entry';

    private ?Connection $connection = null;
    private bool $schemaReady = false;

    public function __construct(
        private readonly ?string $databaseUrl,
        private readonly bool $enabled = false,
        private readonly int $maxRows = 200,
        private readonly bool $strict = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && is_string($this->databaseUrl) && trim($this->databaseUrl) !== '';
    }

    public function initializeSchema(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $connection = $this->connection();
        if ($this->schemaReady || $connection->createSchemaManager()->tablesExist([self::TABLE])) {
            $this->schemaReady = true;
            return;
        }

        $schema = new Schema();
        $table = $schema->createTable(self::TABLE);
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $table->addColumn('tenant_id', Types::STRING, ['length' => 120]);
        $table->addColumn('user_id', Types::STRING, ['length' => 120]);
        $table->addColumn('session_id', Types::STRING, ['length' => 190, 'notnull' => false]);
        $table->addColumn('screen_id', Types::STRING, ['length' => 190]);
        $table->addColumn('dataset_id', Types::STRING, ['length' => 190]);
        $table->addColumn('view_id', Types::STRING, ['length' => 190, 'notnull' => false]);
        $table->addColumn('execution_mode', Types::STRING, ['length' => 32]);
        $table->addColumn('result_source', Types::STRING, ['length' => 32]);
        $table->addColumn('filter_fingerprint', Types::STRING, ['length' => 64]);
        $table->addColumn('row_count', Types::INTEGER);
        $table->addColumn('total_count', Types::INTEGER);
        $table->addColumn('filters_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('parameters_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('sort_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('request_payload_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('result_columns_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('result_rows_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('metadata_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('error_message', Types::TEXT, ['notnull' => false]);
        $table->addColumn('consulted_at', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['tenant_id', 'screen_id', 'consulted_at'], 'idx_analytics_audit_tenant_screen');
        $table->addIndex(['dataset_id', 'consulted_at'], 'idx_analytics_audit_dataset');
        $table->addIndex(['user_id', 'consulted_at'], 'idx_analytics_audit_user');

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->schemaReady = true;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function record(array $entry): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->initializeSchema();
            $connection = $this->connection();
            $rows = is_array($entry['resultRows'] ?? null) ? $entry['resultRows'] : [];
            $columns = is_array($entry['resultColumns'] ?? null) ? $entry['resultColumns'] : [];
            $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
            $rowLimit = max(1, $this->maxRows);
            $truncated = count($rows) > $rowLimit;
            $rows = array_slice($rows, 0, $rowLimit);
            $metadata['rowLimitApplied'] = $rowLimit;
            $metadata['rowsTruncated'] = $truncated;

            $connection->insert(self::TABLE, [
                'tenant_id' => $this->clean((string) ($entry['tenantId'] ?? ''), 120, 'default'),
                'user_id' => $this->clean((string) ($entry['userId'] ?? ''), 120, 'system'),
                'session_id' => $this->nullableString($entry['sessionId'] ?? null, 190),
                'screen_id' => $this->clean((string) ($entry['screenId'] ?? ''), 190, 'analytics'),
                'dataset_id' => $this->clean((string) ($entry['datasetId'] ?? ''), 190, 'dataset'),
                'view_id' => $this->nullableString($entry['viewId'] ?? null, 190),
                'execution_mode' => $this->clean((string) ($entry['executionMode'] ?? 'live'), 32, 'live'),
                'result_source' => $this->clean((string) ($entry['resultSource'] ?? 'live'), 32, 'live'),
                'filter_fingerprint' => $this->clean((string) ($entry['filterFingerprint'] ?? ''), 64, ''),
                'row_count' => (int) ($entry['rowCount'] ?? count($rows)),
                'total_count' => (int) ($entry['totalCount'] ?? 0),
                'filters_json' => $this->normalizeJsonValue($entry['filters'] ?? null),
                'parameters_json' => $this->normalizeJsonValue($entry['parameters'] ?? null),
                'sort_json' => $this->normalizeJsonValue($entry['sort'] ?? null),
                'request_payload_json' => $this->normalizeJsonValue($entry['requestPayload'] ?? null),
                'result_columns_json' => $this->normalizeJsonValue($columns),
                'result_rows_json' => $this->normalizeJsonValue($rows),
                'metadata_json' => $this->normalizeJsonValue($metadata),
                'error_message' => $this->nullableText($entry['errorMessage'] ?? null),
                'consulted_at' => $this->normalizeDateTime($entry['consultedAt'] ?? null),
            ], [
                'filters_json' => Types::JSON,
                'parameters_json' => Types::JSON,
                'sort_json' => Types::JSON,
                'request_payload_json' => Types::JSON,
                'result_columns_json' => Types::JSON,
                'result_rows_json' => Types::JSON,
                'metadata_json' => Types::JSON,
                'consulted_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } catch (\Throwable $error) {
            if ($this->strict) {
                throw $error;
            }
        }
    }

    public function fetchRecent(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $this->initializeSchema();

        return $this->connection()
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('consulted_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function query(array $filters = []): array
    {
        if (!$this->isEnabled()) {
            return ['items' => [], 'total' => 0];
        }

        $this->initializeSchema();
        $connection = $this->connection();
        $limit = max(1, min(300, (int) ($filters['limit'] ?? 120)));

        $qb = $connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('consulted_at', 'DESC')
            ->setMaxResults($limit);

        $countQb = $connection->createQueryBuilder()
            ->select('COUNT(*) AS total')
            ->from(self::TABLE);

        $this->applyQueryFilters($qb, $filters);
        $this->applyQueryFilters($countQb, $filters);

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $total = (int) $countQb->executeQuery()->fetchOne();

        return [
            'items' => array_map(fn (array $row): array => $this->normalizeAuditRow($row), $rows),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function collectFilterOptions(int $limit = 40): array
    {
        if (!$this->isEnabled()) {
            return [
                'tenantIds' => [],
                'userIds' => [],
                'screenIds' => [],
                'datasetIds' => [],
                'resultSources' => [],
            ];
        }

        $this->initializeSchema();
        $connection = $this->connection();
        $build = function (string $column) use ($connection, $limit): array {
            $rows = $connection->createQueryBuilder()
                ->select($column)
                ->from(self::TABLE)
                ->where($column . ' IS NOT NULL')
                ->andWhere($column . " <> ''")
                ->groupBy($column)
                ->orderBy('MAX(consulted_at)', 'DESC')
                ->setMaxResults($limit)
                ->executeQuery()
                ->fetchFirstColumn();

            return array_values(array_filter(array_map('strval', $rows)));
        };

        return [
            'tenantIds' => $build('tenant_id'),
            'userIds' => $build('user_id'),
            'screenIds' => $build('screen_id'),
            'datasetIds' => $build('dataset_id'),
            'resultSources' => $build('result_source'),
        ];
    }

    private function connection(): Connection
    {
        if (!$this->connection instanceof Connection) {
            $params = (new DsnParser([
                'mysql' => 'pdo_mysql',
                'mysql2' => 'pdo_mysql',
                'postgres' => 'pdo_pgsql',
                'postgresql' => 'pdo_pgsql',
                'pgsql' => 'pdo_pgsql',
                'sqlite' => 'pdo_sqlite',
            ]))->parse((string) $this->databaseUrl);
            $this->connection = DriverManager::getConnection($params);
        }

        return $this->connection;
    }

    private function clean(string $value, int $maxLength, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, 4000);
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }

    private function normalizeDateTime(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value) && trim($value) !== '') {
            return new \DateTimeImmutable($value);
        }

        return new \DateTimeImmutable();
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

    /**
     * @param array<string, mixed> $filters
     */
    private function applyQueryFilters(\Doctrine\DBAL\Query\QueryBuilder $qb, array $filters): void
    {
        $map = [
            'tenantId' => 'tenant_id',
            'userId' => 'user_id',
            'screenId' => 'screen_id',
            'datasetId' => 'dataset_id',
            'resultSource' => 'result_source',
        ];
        foreach ($map as $filterKey => $column) {
            $value = trim((string) ($filters[$filterKey] ?? ''));
            if ($value === '') {
                continue;
            }
            $qb->andWhere($column . ' = :' . $filterKey)->setParameter($filterKey, $value);
        }

        $dateFrom = trim((string) ($filters['dateFrom'] ?? ''));
        if ($dateFrom !== '') {
            $qb->andWhere('consulted_at >= :dateFrom')->setParameter('dateFrom', $dateFrom . ' 00:00:00');
        }
        $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateTo !== '') {
            $qb->andWhere('consulted_at <= :dateTo')->setParameter('dateTo', $dateTo . ' 23:59:59');
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeAuditRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'userId' => (string) ($row['user_id'] ?? ''),
            'sessionId' => $row['session_id'] ?? null,
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'datasetId' => (string) ($row['dataset_id'] ?? ''),
            'viewId' => $row['view_id'] ?? null,
            'executionMode' => (string) ($row['execution_mode'] ?? ''),
            'resultSource' => (string) ($row['result_source'] ?? ''),
            'filterFingerprint' => (string) ($row['filter_fingerprint'] ?? ''),
            'rowCount' => (int) ($row['row_count'] ?? 0),
            'totalCount' => (int) ($row['total_count'] ?? 0),
            'filters' => $this->decodeJsonColumn($row['filters_json'] ?? null),
            'parameters' => $this->decodeJsonColumn($row['parameters_json'] ?? null),
            'sort' => $this->decodeJsonColumn($row['sort_json'] ?? null),
            'requestPayload' => $this->decodeJsonColumn($row['request_payload_json'] ?? null),
            'resultColumns' => $this->decodeJsonColumn($row['result_columns_json'] ?? null),
            'resultRows' => $this->decodeJsonColumn($row['result_rows_json'] ?? null),
            'metadata' => $this->decodeJsonColumn($row['metadata_json'] ?? null),
            'errorMessage' => $row['error_message'] ?? null,
            'consultedAt' => $this->formatDateTime($row['consulted_at'] ?? null),
        ];
    }

    private function decodeJsonColumn(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
