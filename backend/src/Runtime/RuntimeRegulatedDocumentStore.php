<?php

namespace App\Runtime;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;

class RuntimeRegulatedDocumentStore
{
    private const RECORD_TABLE = 'runtime_regulated_document_record';
    private const EVENT_TABLE = 'runtime_regulated_document_event';

    private ?Connection $connection = null;
    private bool $schemaReady = false;

    public function __construct(
        private readonly ?string $databaseUrl,
        private readonly bool $enabled = false,
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
        if ($this->schemaReady || $connection->createSchemaManager()->tablesExist([self::RECORD_TABLE, self::EVENT_TABLE])) {
            $this->schemaReady = true;
            return;
        }

        $schema = new Schema();
        $record = $schema->createTable(self::RECORD_TABLE);
        $record->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $record->addColumn('issue_id', Types::STRING, ['length' => 120]);
        $record->addColumn('tenant_id', Types::STRING, ['length' => 120]);
        $record->addColumn('user_id', Types::STRING, ['length' => 120]);
        $record->addColumn('session_id', Types::STRING, ['length' => 190, 'notnull' => false]);
        $record->addColumn('screen_id', Types::STRING, ['length' => 190]);
        $record->addColumn('document_id', Types::STRING, ['length' => 190]);
        $record->addColumn('track', Types::STRING, ['length' => 32]);
        $record->addColumn('document_type', Types::STRING, ['length' => 80]);
        $record->addColumn('compliance_profile', Types::STRING, ['length' => 40]);
        $record->addColumn('state', Types::STRING, ['length' => 32]);
        $record->addColumn('format', Types::STRING, ['length' => 16, 'notnull' => false]);
        $record->addColumn('hash', Types::STRING, ['length' => 80, 'notnull' => false]);
        $record->addColumn('parameters_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('canonical_payload_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('artifact_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('validation_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('verification_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('metadata_json', Types::JSON, ['notnull' => false]);
        $record->addColumn('error_message', Types::TEXT, ['notnull' => false]);
        $record->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $record->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $record->addColumn('issued_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $record->addColumn('verified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $record->setPrimaryKey(['id']);
        $record->addUniqueIndex(['issue_id'], 'uniq_regulated_document_issue');
        $record->addIndex(['hash'], 'idx_regulated_document_hash');
        $record->addIndex(['tenant_id', 'screen_id', 'created_at'], 'idx_regulated_document_screen');
        $record->addIndex(['track', 'document_type', 'state'], 'idx_regulated_document_track_state');

        $event = $schema->createTable(self::EVENT_TABLE);
        $event->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $event->addColumn('issue_id', Types::STRING, ['length' => 120]);
        $event->addColumn('event_type', Types::STRING, ['length' => 40]);
        $event->addColumn('payload_json', Types::JSON, ['notnull' => false]);
        $event->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $event->setPrimaryKey(['id']);
        $event->addIndex(['issue_id', 'created_at'], 'idx_regulated_document_event_issue');

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->schemaReady = true;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function saveRecord(array $record): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->initializeSchema();
            $connection = $this->connection();
            $issueId = $this->clean((string) ($record['issueId'] ?? ''), 120, '');
            if ($issueId === '') {
                throw new \RuntimeException('issueId obrigatorio.');
            }

            $data = [
                'issue_id' => $issueId,
                'tenant_id' => $this->clean((string) ($record['tenantId'] ?? ''), 120, 'default'),
                'user_id' => $this->clean((string) ($record['userId'] ?? ''), 120, 'system'),
                'session_id' => $this->nullableString($record['sessionId'] ?? null, 190),
                'screen_id' => $this->clean((string) ($record['screenId'] ?? ''), 190, 'regulated_document'),
                'document_id' => $this->clean((string) ($record['documentId'] ?? ''), 190, 'documento-regulado'),
                'track' => $this->clean((string) ($record['track'] ?? ''), 32, 'fiscal'),
                'document_type' => $this->clean((string) ($record['documentType'] ?? ''), 80, 'regulated_document'),
                'compliance_profile' => $this->clean((string) ($record['complianceProfile'] ?? ''), 40, 'near_homologated'),
                'state' => $this->clean((string) ($record['state'] ?? ''), 32, 'prepared'),
                'format' => $this->nullableString($record['format'] ?? null, 16),
                'hash' => $this->nullableString($record['hash'] ?? null, 80),
                'parameters_json' => $this->normalizeJsonValue($record['parameters'] ?? null),
                'canonical_payload_json' => $this->normalizeJsonValue($record['canonicalPayload'] ?? null),
                'artifact_json' => $this->normalizeJsonValue($record['artifact'] ?? null),
                'validation_json' => $this->normalizeJsonValue($record['validation'] ?? null),
                'verification_json' => $this->normalizeJsonValue($record['verification'] ?? null),
                'metadata_json' => $this->normalizeJsonValue($record['metadata'] ?? null),
                'error_message' => $this->nullableText($record['errorMessage'] ?? null),
                'created_at' => $this->normalizeDateTime($record['createdAt'] ?? null),
                'updated_at' => $this->normalizeDateTime($record['updatedAt'] ?? null),
                'issued_at' => $this->nullableDateTime($record['issuedAt'] ?? null),
                'verified_at' => $this->nullableDateTime($record['verifiedAt'] ?? null),
            ];

            $existing = $connection->createQueryBuilder()
                ->select('id')
                ->from(self::RECORD_TABLE)
                ->where('issue_id = :issueId')
                ->setParameter('issueId', $issueId)
                ->executeQuery()
                ->fetchOne();

            if ($existing !== false) {
                $connection->update(self::RECORD_TABLE, $data, ['issue_id' => $issueId], $this->types());
            } else {
                $connection->insert(self::RECORD_TABLE, $data, $this->types());
            }
        } catch (\Throwable $error) {
            if ($this->strict) {
                throw $error;
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function appendEvent(string $issueId, string $eventType, array $payload = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->initializeSchema();
            $this->connection()->insert(self::EVENT_TABLE, [
                'issue_id' => $this->clean($issueId, 120, ''),
                'event_type' => $this->clean($eventType, 40, 'event'),
                'payload_json' => $this->normalizeJsonValue($payload),
                'created_at' => new \DateTimeImmutable(),
            ], [
                'payload_json' => Types::JSON,
                'created_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } catch (\Throwable $error) {
            if ($this->strict) {
                throw $error;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIssueId(string $issueId): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $this->initializeSchema();
        $row = $this->connection()->createQueryBuilder()
            ->select('*')
            ->from(self::RECORD_TABLE)
            ->where('issue_id = :issueId')
            ->setParameter('issueId', $issueId)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->normalizeRecordRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $this->initializeSchema();
        $row = $this->connection()->createQueryBuilder()
            ->select('*')
            ->from(self::RECORD_TABLE)
            ->where('hash = :hash')
            ->orderBy('updated_at', 'DESC')
            ->setMaxResults(1)
            ->setParameter('hash', $hash)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->normalizeRecordRow($row) : null;
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
        $limit = max(1, min(300, (int) ($filters['limit'] ?? 120)));
        $connection = $this->connection();

        $qb = $connection->createQueryBuilder()
            ->select('*')
            ->from(self::RECORD_TABLE)
            ->orderBy('updated_at', 'DESC')
            ->setMaxResults($limit);
        $countQb = $connection->createQueryBuilder()
            ->select('COUNT(*) AS total')
            ->from(self::RECORD_TABLE);

        $this->applyQueryFilters($qb, $filters);
        $this->applyQueryFilters($countQb, $filters);

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $total = (int) $countQb->executeQuery()->fetchOne();

        return [
            'items' => array_map(fn (array $row): array => $this->normalizeRecordRow($row), $rows),
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
                'tracks' => [],
                'documentTypes' => [],
                'states' => [],
            ];
        }

        $this->initializeSchema();
        $connection = $this->connection();
        $build = function (string $column) use ($connection, $limit): array {
            $rows = $connection->createQueryBuilder()
                ->select($column)
                ->from(self::RECORD_TABLE)
                ->where($column . ' IS NOT NULL')
                ->andWhere($column . " <> ''")
                ->groupBy($column)
                ->orderBy('MAX(updated_at)', 'DESC')
                ->setMaxResults($limit)
                ->executeQuery()
                ->fetchFirstColumn();

            return array_values(array_filter(array_map('strval', $rows)));
        };

        return [
            'tenantIds' => $build('tenant_id'),
            'userIds' => $build('user_id'),
            'screenIds' => $build('screen_id'),
            'tracks' => $build('track'),
            'documentTypes' => $build('document_type'),
            'states' => $build('state'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(string $issueId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $this->initializeSchema();
        $rows = $this->connection()->createQueryBuilder()
            ->select('*')
            ->from(self::EVENT_TABLE)
            ->where('issue_id = :issueId')
            ->orderBy('created_at', 'ASC')
            ->setParameter('issueId', $issueId)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'issueId' => (string) ($row['issue_id'] ?? ''),
                'eventType' => (string) ($row['event_type'] ?? ''),
                'payload' => is_array($row['payload_json'] ?? null) ? $row['payload_json'] : $this->decodeJson($row['payload_json'] ?? null),
                'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function collectObservabilitySummary(int $recentLimit = 20): array
    {
        if (!$this->isEnabled()) {
            return [
                'total' => 0,
                'withHash' => 0,
                'withArtifact' => 0,
                'withCanonicalPayload' => 0,
                'verified' => 0,
                'failed' => 0,
                'byState' => [],
                'byTrack' => [],
                'newestUpdatedAt' => null,
                'oldestUpdatedAt' => null,
                'recentIssues' => [],
            ];
        }

        $this->initializeSchema();
        $items = $this->query(['limit' => max(1, min(100, $recentLimit))])['items'];
        $rows = $this->connection()->createQueryBuilder()
            ->select('state', 'track', 'hash', 'artifact_json', 'canonical_payload_json', 'updated_at')
            ->from(self::RECORD_TABLE)
            ->executeQuery()
            ->fetchAllAssociative();

        $summary = [
            'total' => count($rows),
            'withHash' => 0,
            'withArtifact' => 0,
            'withCanonicalPayload' => 0,
            'verified' => 0,
            'failed' => 0,
            'byState' => [],
            'byTrack' => [],
            'newestUpdatedAt' => null,
            'oldestUpdatedAt' => null,
            'recentIssues' => array_map(static fn (array $item): array => [
                'issueId' => (string) ($item['issueId'] ?? ''),
                'track' => (string) ($item['track'] ?? ''),
                'documentType' => (string) ($item['documentType'] ?? ''),
                'state' => (string) ($item['state'] ?? ''),
                'updatedAt' => $item['updatedAt'] ?? null,
            ], $items),
        ];

        foreach ($rows as $row) {
            $state = (string) ($row['state'] ?? '');
            $track = (string) ($row['track'] ?? '');
            if ($state !== '') {
                $summary['byState'][$state] = (int) ($summary['byState'][$state] ?? 0) + 1;
            }
            if ($track !== '') {
                $summary['byTrack'][$track] = (int) ($summary['byTrack'][$track] ?? 0) + 1;
            }
            if (trim((string) ($row['hash'] ?? '')) !== '') {
                ++$summary['withHash'];
            }
            $artifact = is_array($row['artifact_json'] ?? null) ? $row['artifact_json'] : $this->decodeJson($row['artifact_json'] ?? null);
            if (!empty($artifact['contentBase64'])) {
                ++$summary['withArtifact'];
            }
            $canonical = is_array($row['canonical_payload_json'] ?? null) ? $row['canonical_payload_json'] : $this->decodeJson($row['canonical_payload_json'] ?? null);
            if ($canonical !== []) {
                ++$summary['withCanonicalPayload'];
            }
            if ($state === 'verified') {
                ++$summary['verified'];
            }
            if ($state === 'failed') {
                ++$summary['failed'];
            }
            $updatedAt = $this->formatDateTime($row['updated_at'] ?? null);
            if ($updatedAt !== null && ($summary['newestUpdatedAt'] === null || $updatedAt > $summary['newestUpdatedAt'])) {
                $summary['newestUpdatedAt'] = $updatedAt;
            }
            if ($updatedAt !== null && ($summary['oldestUpdatedAt'] === null || $updatedAt < $summary['oldestUpdatedAt'])) {
                $summary['oldestUpdatedAt'] = $updatedAt;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function cleanupExpiredData(bool $apply = false, ?\DateTimeImmutable $now = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'enabled' => false,
                'recordsScanned' => 0,
                'payloadsCleared' => 0,
                'artifactsCleared' => 0,
                'eventsDeleted' => 0,
                'affectedIssueIds' => [],
            ];
        }

        $this->initializeSchema();
        $now ??= new \DateTimeImmutable();
        $items = $this->query(['limit' => 3000])['items'];
        $connection = $this->connection();

        $payloadsCleared = 0;
        $artifactsCleared = 0;
        $eventsDeleted = 0;
        $affectedIssueIds = [];

        foreach ($items as $item) {
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $retention = is_array($metadata['retention'] ?? null) ? $metadata['retention'] : [];
            $storeDays = max(1, min(3650, (int) ($retention['storeDays'] ?? 365)));
            $keepPayload = ($retention['keepPayload'] ?? true) !== false;
            $keepArtifact = ($retention['keepArtifact'] ?? true) !== false;
            $updatedAt = new \DateTimeImmutable((string) ($item['updatedAt'] ?? $item['createdAt'] ?? $now->format(DATE_ATOM)));
            $cutoff = $updatedAt->modify('+' . $storeDays . ' days');
            $shouldExpire = $now >= $cutoff;
            $recordChanged = false;
            $record = $item;

            if (!$keepPayload || $shouldExpire) {
                if (!empty($record['canonicalPayload'])) {
                    ++$payloadsCleared;
                    $record['canonicalPayload'] = null;
                    $recordChanged = true;
                }
            }
            if (!$keepArtifact || $shouldExpire) {
                $artifact = is_array($record['artifact'] ?? null) ? $record['artifact'] : [];
                if (!empty($artifact['contentBase64'])) {
                    ++$artifactsCleared;
                    $record['artifact'] = [
                        'stored' => false,
                        'format' => (string) ($artifact['format'] ?? ''),
                        'fileName' => (string) ($artifact['fileName'] ?? ''),
                        'contentType' => (string) ($artifact['contentType'] ?? 'application/octet-stream'),
                    ];
                    $recordChanged = true;
                }
            }
            if ($recordChanged) {
                $record['updatedAt'] = $now;
                $affectedIssueIds[] = (string) ($record['issueId'] ?? '');
                if ($apply) {
                    $this->saveRecord($record);
                }
            }
            if ($shouldExpire) {
                $eventCount = count($this->fetchEvents((string) ($item['issueId'] ?? '')));
                if ($eventCount > 0) {
                    $eventsDeleted += $eventCount;
                    if ($apply) {
                        $connection->delete(self::EVENT_TABLE, ['issue_id' => (string) ($item['issueId'] ?? '')]);
                    }
                }
            }
        }

        return [
            'enabled' => true,
            'recordsScanned' => count($items),
            'payloadsCleared' => $payloadsCleared,
            'artifactsCleared' => $artifactsCleared,
            'eventsDeleted' => $eventsDeleted,
            'affectedIssueIds' => array_values(array_unique(array_filter($affectedIssueIds))),
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

    private function types(): array
    {
        return [
            'parameters_json' => Types::JSON,
            'canonical_payload_json' => Types::JSON,
            'artifact_json' => Types::JSON,
            'validation_json' => Types::JSON,
            'verification_json' => Types::JSON,
            'metadata_json' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
            'issued_at' => Types::DATETIME_IMMUTABLE,
            'verified_at' => Types::DATETIME_IMMUTABLE,
        ];
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
            'track' => 'track',
            'documentType' => 'document_type',
            'state' => 'state',
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
            $qb->andWhere('updated_at >= :dateFrom')->setParameter('dateFrom', $dateFrom . ' 00:00:00');
        }
        $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateTo !== '') {
            $qb->andWhere('updated_at <= :dateTo')->setParameter('dateTo', $dateTo . ' 23:59:59');
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRecordRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'issueId' => (string) ($row['issue_id'] ?? ''),
            'tenantId' => (string) ($row['tenant_id'] ?? ''),
            'userId' => (string) ($row['user_id'] ?? ''),
            'sessionId' => (string) ($row['session_id'] ?? ''),
            'screenId' => (string) ($row['screen_id'] ?? ''),
            'documentId' => (string) ($row['document_id'] ?? ''),
            'track' => (string) ($row['track'] ?? ''),
            'documentType' => (string) ($row['document_type'] ?? ''),
            'complianceProfile' => (string) ($row['compliance_profile'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'format' => (string) ($row['format'] ?? ''),
            'hash' => (string) ($row['hash'] ?? ''),
            'parameters' => is_array($row['parameters_json'] ?? null) ? $row['parameters_json'] : $this->decodeJson($row['parameters_json'] ?? null),
            'canonicalPayload' => is_array($row['canonical_payload_json'] ?? null) ? $row['canonical_payload_json'] : $this->decodeJson($row['canonical_payload_json'] ?? null),
            'artifact' => is_array($row['artifact_json'] ?? null) ? $row['artifact_json'] : $this->decodeJson($row['artifact_json'] ?? null),
            'validation' => is_array($row['validation_json'] ?? null) ? $row['validation_json'] : $this->decodeJson($row['validation_json'] ?? null),
            'verification' => is_array($row['verification_json'] ?? null) ? $row['verification_json'] : $this->decodeJson($row['verification_json'] ?? null),
            'metadata' => is_array($row['metadata_json'] ?? null) ? $row['metadata_json'] : $this->decodeJson($row['metadata_json'] ?? null),
            'errorMessage' => (string) ($row['error_message'] ?? ''),
            'createdAt' => $this->formatDateTime($row['created_at'] ?? null),
            'updatedAt' => $this->formatDateTime($row['updated_at'] ?? null),
            'issuedAt' => $this->formatDateTime($row['issued_at'] ?? null),
            'verifiedAt' => $this->formatDateTime($row['verified_at'] ?? null),
        ];
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
        return $value === '' ? null : mb_substr($value, 0, 16000);
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

    private function nullableDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeDateTime($value);
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
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
