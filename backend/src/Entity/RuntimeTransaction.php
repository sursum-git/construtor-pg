<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeTransactionRepository::class)]
#[ORM\Table(name: 'runtime_transaction')]
#[ORM\Index(columns: ['tenant_id', 'session_id', 'started_at'], name: 'idx_runtime_transaction_session')]
#[ORM\Index(columns: ['entity_code', 'record_id'], name: 'idx_runtime_transaction_record')]
class RuntimeTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

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

    #[ORM\Column(length: 160)]
    private string $endpointId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $actionId = null;

    #[ORM\Column(length: 80)]
    private string $operation = '';

    #[ORM\Column(length: 30)]
    private string $status = 'opened';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $lockToken = null;

    #[ORM\Column(type: Types::JSON)]
    private array $requestContext = [];

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
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

    public function getEndpointId(): string
    {
        return $this->endpointId;
    }

    public function setEndpointId(string $endpointId): self
    {
        $this->endpointId = mb_substr($endpointId, 0, 160);
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

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = mb_substr($operation, 0, 80);
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

    public function getLockToken(): ?string
    {
        return $this->lockToken;
    }

    public function setLockToken(?string $lockToken): self
    {
        $this->lockToken = $lockToken === null || $lockToken === '' ? null : mb_substr($lockToken, 0, 120);
        return $this;
    }

    public function getRequestContext(): array
    {
        return $this->requestContext;
    }

    public function setRequestContext(array $requestContext): self
    {
        $this->requestContext = $requestContext;
        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function finish(string $status): self
    {
        $this->status = mb_substr($status, 0, 30);
        $this->finishedAt = new \DateTimeImmutable();
        return $this;
    }
}
