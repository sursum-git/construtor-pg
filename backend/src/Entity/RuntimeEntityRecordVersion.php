<?php

namespace App\Entity;

use App\Repository\RuntimeEntityRecordVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RuntimeEntityRecordVersionRepository::class)]
#[ORM\Table(name: 'runtime_entity_record_version')]
#[ORM\Index(name: 'idx_runtime_entity_record_version_record', columns: ['entity_code', 'record_id', 'revision'])]
#[ORM\Index(name: 'idx_runtime_entity_record_version_lookup', columns: ['entity_code', 'record_id', 'created_at'])]
class RuntimeEntityRecordVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $entityCode = '';

    #[ORM\Column(length: 120)]
    private string $recordId = '';

    #[ORM\Column]
    private int $revision = 1;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $snapshotHash = null;

    #[ORM\Column(type: Types::JSON)]
    private array $snapshot = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sourceUpdatedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityCode(): string
    {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): self
    {
        $this->entityCode = mb_substr($entityCode, 0, 120);
        return $this;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }

    public function setRecordId(string $recordId): self
    {
        $this->recordId = mb_substr($recordId, 0, 120);
        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): self
    {
        $this->revision = max(1, $revision);
        return $this;
    }

    public function getSnapshotHash(): ?string
    {
        return $this->snapshotHash;
    }

    public function setSnapshotHash(?string $snapshotHash): self
    {
        $this->snapshotHash = $snapshotHash === null || $snapshotHash === '' ? null : mb_substr($snapshotHash, 0, 64);
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): self
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getSourceUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->sourceUpdatedAt;
    }

    public function setSourceUpdatedAt(?\DateTimeImmutable $sourceUpdatedAt): self
    {
        $this->sourceUpdatedAt = $sourceUpdatedAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
