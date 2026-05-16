<?php

namespace App\Entity;

use App\Repository\SystemRecordIntegrityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemRecordIntegrityRepository::class)]
#[ORM\Table(name: 'system_record_integrity')]
#[ORM\UniqueConstraint(name: 'uniq_system_record_integrity_target', columns: ['table_name', 'record_id'])]
#[ORM\Index(name: 'idx_system_record_integrity_table', columns: ['table_name', 'signed_at'])]
class SystemRecordIntegrity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $tableName = '';

    #[ORM\Column]
    private int $recordId = 0;

    #[ORM\Column]
    private int $integritySchemaVersion = 1;

    #[ORM\Column(length: 64)]
    private string $payloadHash = '';

    #[ORM\Column(length: 128)]
    private string $signature = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $signedBy = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $signedAt;

    #[ORM\Column(length: 20)]
    private string $lastCheckStatus = 'pending';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastErrorMessage = null;

    public function __construct()
    {
        $this->signedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = mb_substr(trim($tableName), 0, 120);
        return $this;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function setRecordId(int $recordId): self
    {
        $this->recordId = max(0, $recordId);
        return $this;
    }

    public function getIntegritySchemaVersion(): int
    {
        return $this->integritySchemaVersion;
    }

    public function setIntegritySchemaVersion(int $integritySchemaVersion): self
    {
        $this->integritySchemaVersion = max(1, $integritySchemaVersion);
        return $this;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function setPayloadHash(string $payloadHash): self
    {
        $this->payloadHash = mb_substr(trim($payloadHash), 0, 64);
        return $this;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): self
    {
        $this->signature = mb_substr(trim($signature), 0, 128);
        return $this;
    }

    public function getSignedBy(): ?string
    {
        return $this->signedBy;
    }

    public function setSignedBy(?string $signedBy): self
    {
        $this->signedBy = $signedBy === null || $signedBy === '' ? null : mb_substr(trim($signedBy), 0, 120);
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getSignedAt(): \DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(\DateTimeImmutable $signedAt): self
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getLastCheckStatus(): string
    {
        return $this->lastCheckStatus;
    }

    public function setLastCheckStatus(string $lastCheckStatus): self
    {
        $this->lastCheckStatus = mb_substr(trim($lastCheckStatus), 0, 20);
        return $this;
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(?\DateTimeImmutable $lastCheckedAt): self
    {
        $this->lastCheckedAt = $lastCheckedAt;
        return $this;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): self
    {
        $this->lastErrorMessage = $lastErrorMessage === null || $lastErrorMessage === '' ? null : mb_substr(trim($lastErrorMessage), 0, 255);
        return $this;
    }
}
