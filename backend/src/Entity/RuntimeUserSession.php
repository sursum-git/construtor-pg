<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeUserSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeUserSessionRepository::class)]
#[ORM\Table(name: 'runtime_user_session')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_user_session_tenant_session', columns: ['tenant_id', 'session_id'])]
#[ORM\Index(columns: ['tenant_id', 'user_id', 'status'], name: 'idx_runtime_user_session_user')]
class RuntimeUserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $phpSessionId = null;

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column]
    private \DateTimeImmutable $enteredAt;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $operatingSystem = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column]
    private bool $isMobile = false;

    #[ORM\Column(type: Types::JSON)]
    private array $sessionProperties = [];

    #[ORM\Column(type: Types::JSON)]
    private array $permissionSnapshot = [];

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $revokedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $revokeReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->enteredAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->lastSeenAt = $now;
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

    public function getPhpSessionId(): ?string
    {
        return $this->phpSessionId;
    }

    public function setPhpSessionId(?string $phpSessionId): self
    {
        $this->phpSessionId = $phpSessionId === null || $phpSessionId === '' ? null : mb_substr($phpSessionId, 0, 160);
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

    public function getEnteredAt(): \DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function setEnteredAt(\DateTimeImmutable $enteredAt): self
    {
        $this->enteredAt = $enteredAt;
        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): self
    {
        $this->deviceName = $deviceName === null || $deviceName === '' ? null : mb_substr($deviceName, 0, 160);
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 1000);
        return $this;
    }

    public function getOperatingSystem(): ?string
    {
        return $this->operatingSystem;
    }

    public function setOperatingSystem(?string $operatingSystem): self
    {
        $this->operatingSystem = $operatingSystem === null || $operatingSystem === '' ? null : mb_substr($operatingSystem, 0, 80);
        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function setBrowser(?string $browser): self
    {
        $this->browser = $browser === null || $browser === '' ? null : mb_substr($browser, 0, 80);
        return $this;
    }

    public function isMobile(): bool
    {
        return $this->isMobile;
    }

    public function setIsMobile(bool $isMobile): self
    {
        $this->isMobile = $isMobile;
        return $this;
    }

    public function getSessionProperties(): array
    {
        return $this->sessionProperties;
    }

    public function setSessionProperties(array $sessionProperties): self
    {
        $this->sessionProperties = $sessionProperties;
        return $this;
    }

    public function markPhpSessionKillRequested(): self
    {
        $this->sessionProperties['phpSessionKillRequested'] = true;
        $this->sessionProperties['phpSessionKillRequestedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->touch();
        return $this;
    }

    public function markPhpSessionInvalidated(): self
    {
        $this->sessionProperties['phpSessionInvalidated'] = true;
        $this->sessionProperties['phpSessionInvalidatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->touch();
        return $this;
    }

    public function getPermissionSnapshot(): array
    {
        return $this->permissionSnapshot;
    }

    public function setPermissionSnapshot(array $permissionSnapshot): self
    {
        $this->permissionSnapshot = $permissionSnapshot;
        return $this;
    }

    public function getRevokedBy(): ?string
    {
        return $this->revokedBy;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokeReason(): ?string
    {
        return $this->revokeReason;
    }

    public function revoke(string $revokedBy, string $reason): self
    {
        $this->status = 'revoked';
        $this->revokedBy = mb_substr($revokedBy, 0, 120);
        $this->revokeReason = mb_substr($reason, 0, 255);
        $this->revokedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(): self
    {
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;
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
}
