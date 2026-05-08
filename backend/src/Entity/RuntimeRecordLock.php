<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeRecordLockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeRecordLockRepository::class)]
#[ORM\Table(name: 'runtime_record_lock')]
#[ORM\UniqueConstraint(name: 'UNIQ_RUNTIME_RECORD_LOCK_TOKEN', columns: ['lock_token'])]
#[ORM\Index(columns: ['tenant_id', 'entity_code', 'record_id', 'status'], name: 'idx_runtime_record_lock_lookup')]
#[ORM\Index(columns: ['transaction_id'], name: 'IDX_RUNTIME_RECORD_LOCK_TRANSACTION')]
class RuntimeRecordLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $programId = null;

    #[ORM\Column(length: 120)]
    private string $entityCode = '';

    #[ORM\Column(length: 120)]
    private string $recordId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $actionId = null;

    #[ORM\Column(length: 120)]
    private string $lockToken = '';

    #[ORM\ManyToOne(targetEntity: RuntimeTransaction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RuntimeTransaction $transaction = null;

    #[ORM\Column(length: 120)]
    private string $lockedByUserId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $lockedByUserName = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 20)]
    private string $mode = 'block';

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column]
    private \DateTimeImmutable $acquiredAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->acquiredAt = $now;
        $this->lastSeenAt = $now;
        $this->expiresAt = $now->modify('+300 seconds');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr($tenantId, 0, 80);
        return $this;
    }

    public function getProgramId(): ?string
    {
        return $this->programId;
    }

    public function setProgramId(?string $programId): self
    {
        $this->programId = $programId === null || $programId === '' ? null : mb_substr($programId, 0, 160);
        return $this;
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

    public function setRecordId(string|int $recordId): self
    {
        $this->recordId = mb_substr((string) $recordId, 0, 120);
        return $this;
    }

    public function getActionId(): ?string
    {
        return $this->actionId;
    }

    public function setActionId(?string $actionId): self
    {
        $this->actionId = $actionId === null || $actionId === '' ? null : mb_substr($actionId, 0, 160);
        return $this;
    }

    public function getLockToken(): string
    {
        return $this->lockToken;
    }

    public function setLockToken(string $lockToken): self
    {
        $this->lockToken = mb_substr($lockToken, 0, 120);
        return $this;
    }

    public function getTransaction(): ?RuntimeTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(?RuntimeTransaction $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getLockedByUserId(): string
    {
        return $this->lockedByUserId;
    }

    public function setLockedByUserId(string $lockedByUserId): self
    {
        $this->lockedByUserId = mb_substr($lockedByUserId, 0, 120);
        return $this;
    }

    public function getLockedByUserName(): ?string
    {
        return $this->lockedByUserName;
    }

    public function setLockedByUserName(?string $lockedByUserName): self
    {
        $this->lockedByUserName = $lockedByUserName === null ? null : mb_substr($lockedByUserName, 0, 160);
        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = mb_substr($sessionId, 0, 160);
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = mb_substr($mode, 0, 20);
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr($status, 0, 30);
        return $this;
    }

    public function getAcquiredAt(): \DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function release(string $status = 'released'): self
    {
        $this->status = mb_substr($status, 0, 30);
        $this->releasedAt = new \DateTimeImmutable();
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
}
