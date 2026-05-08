<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeLockPolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeLockPolicyRepository::class)]
#[ORM\Table(name: 'runtime_lock_policy')]
#[ORM\Index(columns: ['tenant_id', 'program_id', 'entity_code', 'action_id'], name: 'idx_runtime_lock_policy_scope')]
class RuntimeLockPolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $tenantId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $programId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $entityCode = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $actionId = null;

    #[ORM\Column(length: 20)]
    private string $mode = 'block';

    #[ORM\Column(length: 20)]
    private string $stalePolicy = 'block';

    #[ORM\Column]
    private int $lockTtlSeconds = 300;

    #[ORM\Column]
    private int $heartbeatIntervalSeconds = 60;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $handlerId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $conditionConfig = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

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

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): self
    {
        $this->tenantId = $tenantId === null || $tenantId === '' ? null : mb_substr($tenantId, 0, 80);
        $this->touch();
        return $this;
    }

    public function getProgramId(): ?string
    {
        return $this->programId;
    }

    public function setProgramId(?string $programId): self
    {
        $this->programId = $programId === null || $programId === '' ? null : mb_substr($programId, 0, 160);
        $this->touch();
        return $this;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(?string $entityCode): self
    {
        $this->entityCode = $entityCode === null || $entityCode === '' ? null : mb_substr($entityCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getActionId(): ?string
    {
        return $this->actionId;
    }

    public function setActionId(?string $actionId): self
    {
        $this->actionId = $actionId === null || $actionId === '' ? null : mb_substr($actionId, 0, 160);
        $this->touch();
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = in_array($mode, ['none', 'warn', 'block'], true) ? $mode : 'block';
        $this->touch();
        return $this;
    }

    public function getStalePolicy(): string
    {
        return $this->stalePolicy;
    }

    public function setStalePolicy(string $stalePolicy): self
    {
        $this->stalePolicy = in_array($stalePolicy, ['allow', 'warn', 'block'], true) ? $stalePolicy : 'block';
        $this->touch();
        return $this;
    }

    public function getLockTtlSeconds(): int
    {
        return $this->lockTtlSeconds;
    }

    public function setLockTtlSeconds(int $lockTtlSeconds): self
    {
        $this->lockTtlSeconds = max(30, min(86400, $lockTtlSeconds));
        $this->touch();
        return $this;
    }

    public function getHeartbeatIntervalSeconds(): int
    {
        return $this->heartbeatIntervalSeconds;
    }

    public function setHeartbeatIntervalSeconds(int $heartbeatIntervalSeconds): self
    {
        $this->heartbeatIntervalSeconds = max(10, min(3600, $heartbeatIntervalSeconds));
        $this->touch();
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();
        return $this;
    }

    public function getHandlerId(): ?string
    {
        return $this->handlerId;
    }

    public function setHandlerId(?string $handlerId): self
    {
        $this->handlerId = $handlerId === null || $handlerId === '' ? null : mb_substr($handlerId, 0, 160);
        $this->touch();
        return $this;
    }

    public function getConditionConfig(): array
    {
        return $this->conditionConfig;
    }

    public function setConditionConfig(array $conditionConfig): self
    {
        $this->conditionConfig = $conditionConfig;
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

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
