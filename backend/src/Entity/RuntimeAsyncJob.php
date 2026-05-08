<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeAsyncJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeAsyncJobRepository::class)]
#[ORM\Table(name: 'runtime_async_job')]
#[ORM\Index(columns: ['transaction_id'], name: 'idx_runtime_async_job_transaction')]
#[ORM\Index(columns: ['tenant_id', 'status', 'created_at'], name: 'idx_runtime_async_job_status')]
#[ORM\Index(columns: ['entity_code', 'record_id'], name: 'idx_runtime_async_job_record')]
#[ORM\Index(columns: ['job_type', 'status'], name: 'idx_runtime_async_job_type')]
class RuntimeAsyncJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RuntimeTransaction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RuntimeTransaction $transaction = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 160)]
    private string $screenId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $programId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $entityCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $recordId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $actionId = null;

    #[ORM\Column(length: 120)]
    private string $jobType = '';

    #[ORM\Column(length: 30)]
    private string $status = 'queued';

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::JSON)]
    private array $result = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr($tenantId, 0, 80);
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = mb_substr($userId, 0, 120);
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName === null ? null : mb_substr($userName, 0, 160);
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

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = mb_substr($screenId, 0, 160);
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

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(?string $entityCode): self
    {
        $this->entityCode = $entityCode === null || $entityCode === '' ? null : mb_substr($entityCode, 0, 120);
        return $this;
    }

    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    public function setRecordId(null|string|int $recordId): self
    {
        $this->recordId = $recordId === null || $recordId === '' ? null : mb_substr((string) $recordId, 0, 120);
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

    public function getJobType(): string
    {
        return $this->jobType;
    }

    public function setJobType(string $jobType): self
    {
        $this->jobType = mb_substr($jobType, 0, 120);
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr($status, 0, 30);
        $this->touch();
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): self
    {
        ++$this->attempts;
        $this->touch();
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        $this->touch();
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        $this->touch();
        return $this;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): self
    {
        $this->result = $result;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function markRunning(): self
    {
        $now = new \DateTimeImmutable();
        $this->status = 'running';
        $this->startedAt = $now;
        $this->updatedAt = $now;
        $this->incrementAttempts();
        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function markSucceeded(array $result = []): self
    {
        $now = new \DateTimeImmutable();
        $this->status = 'succeeded';
        $this->result = $result;
        $this->lastError = null;
        $this->finishedAt = $now;
        $this->updatedAt = $now;
        return $this;
    }

    public function markFailed(string $error, array $result = []): self
    {
        $now = new \DateTimeImmutable();
        $this->status = 'failed';
        $this->lastError = $error;
        $this->result = $result;
        $this->finishedAt = $now;
        $this->updatedAt = $now;
        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
