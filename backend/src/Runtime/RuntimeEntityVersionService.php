<?php

namespace App\Runtime;

use App\Entity\RuntimeEntityRecordVersion;
use App\Repository\RuntimeEntityRecordVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

class RuntimeEntityVersionService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $snapshotCache = [];

    public function __construct(
        private readonly RuntimeEntityRecordVersionRepository $versions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function snapshot(array $definition, array $row): ?RuntimeEntityRecordVersion
    {
        $versioning = is_array($definition['versioning'] ?? null) ? $definition['versioning'] : [];
        if (($versioning['enabled'] ?? false) !== true) {
            return null;
        }

        $recordId = (string) ($row[$definition['primaryKey']] ?? '');
        if ($recordId === '') {
            return null;
        }

        $snapshot = $this->buildSnapshot($definition, $row);
        $snapshotHash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $latest = $this->versions->findLatest($definition['entityCode'], $recordId);
        $deduplicate = ($versioning['deduplicate'] ?? true) === true;
        if ($latest && $deduplicate && $latest->getSnapshotHash() === $snapshotHash) {
            return $latest;
        }

        $version = (new RuntimeEntityRecordVersion())
            ->setEntityCode($definition['entityCode'])
            ->setRecordId($recordId)
            ->setRevision(($latest?->getRevision() ?? 0) + 1)
            ->setSnapshotHash($snapshotHash)
            ->setSnapshot($snapshot)
            ->setSourceUpdatedAt($this->parseDateTime($row['_runtime']['lastModifiedAt'] ?? null));

        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->snapshotCache[$version->getId() ?? 0] = $snapshot;

        return $version;
    }

    public function resolveCurrentVersionId(string $entityCode, string|int $recordId): ?int
    {
        $item = $this->versions->findLatest($entityCode, (string) $recordId);
        return $item?->getId();
    }

    public function resolveSnapshotValue(int $versionId, string $path): mixed
    {
        $snapshot = $this->loadSnapshot($versionId);
        if (!is_array($snapshot) || $path === '') {
            return null;
        }

        $current = $snapshot;
        foreach (explode('.', $path) as $segment) {
            $key = trim($segment);
            if ($key === '' || !is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * @param list<int> $versionIds
     * @return array<int, array<string, mixed>|null>
     */
    public function resolveSnapshots(array $versionIds): array
    {
        $result = [];
        $missing = [];
        foreach ($versionIds as $id) {
            if (array_key_exists($id, $this->snapshotCache)) {
                $result[$id] = $this->snapshotCache[$id];
                continue;
            }
            $missing[] = $id;
        }

        if ($missing) {
            foreach ($this->versions->findIndexedByIds($missing) as $id => $item) {
                $this->snapshotCache[$id] = $item->getSnapshot();
            }
        }

        foreach ($versionIds as $id) {
            $result[$id] = $this->snapshotCache[$id] ?? null;
        }

        return $result;
    }

    private function loadSnapshot(int $versionId): ?array
    {
        if (array_key_exists($versionId, $this->snapshotCache)) {
            return $this->snapshotCache[$versionId];
        }

        $item = $this->versions->find($versionId);
        $snapshot = $item?->getSnapshot();
        $this->snapshotCache[$versionId] = is_array($snapshot) ? $snapshot : null;

        return $this->snapshotCache[$versionId];
    }

    private function buildSnapshot(array $definition, array $row): array
    {
        $snapshot = [];
        foreach ($definition['fields'] as $field => $config) {
            if (($config['options']['includeInVersion'] ?? true) !== true) {
                continue;
            }
            if (($config['options']['virtual'] ?? false) === true) {
                continue;
            }
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $snapshot[$field] = $row[$field];
        }

        $snapshot['_runtime'] = [
            'entityCode' => $definition['entityCode'],
            'primaryKey' => $definition['primaryKey'],
            'recordId' => $row[$definition['primaryKey']] ?? null,
            'capturedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'sourceLastModifiedAt' => $row['_runtime']['lastModifiedAt'] ?? null,
        ];

        return $snapshot;
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
