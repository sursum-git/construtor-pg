<?php

namespace App\Entity;

use App\Repository\BuilderEditorLockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuilderEditorLockRepository::class)]
#[ORM\Table(name: 'builder_editor_lock')]
#[ORM\UniqueConstraint(name: 'uniq_builder_editor_lock_token', columns: ['lock_token'])]
#[ORM\Index(name: 'idx_builder_editor_lock_scope', columns: ['scope_type', 'scope_code', 'status', 'expires_at'])]
class BuilderEditorLock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $scopeType = '';

    #[ORM\Column(length: 160)]
    private string $scopeCode = '';

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 120)]
    private string $lockToken = '';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(nullable: true)]
    private ?int $grantId = null;

    #[ORM\Column(length: 30)]
    private string $lockCategory = 'general';

    #[ORM\Column]
    private \DateTimeImmutable $acquiredAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->acquiredAt = $now;
        $this->lastSeenAt = $now;
        $this->expiresAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScopeType(): string
    {
        return $this->scopeType;
    }

    public function setScopeType(string $scopeType): self
    {
        $this->scopeType = mb_substr(trim($scopeType), 0, 30);
        $this->touch();
        return $this;
    }

    public function getScopeCode(): string
    {
        return $this->scopeCode;
    }

    public function setScopeCode(string $scopeCode): self
    {
        $this->scopeCode = mb_substr(trim($scopeCode), 0, 160);
        $this->touch();
        return $this;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr(trim($tenantId), 0, 80);
        $this->touch();
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = mb_substr(trim($userId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName === null || $userName === '' ? null : mb_substr(trim($userName), 0, 160);
        $this->touch();
        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = mb_substr(trim($sessionId), 0, 160);
        $this->touch();
        return $this;
    }

    public function getLockToken(): string
    {
        return $this->lockToken;
    }

    public function setLockToken(string $lockToken): self
    {
        $this->lockToken = mb_substr(trim($lockToken), 0, 120);
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20);
        $this->touch();
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName === null || $displayName === '' ? null : mb_substr(trim($displayName), 0, 255);
        $this->touch();
        return $this;
    }

    public function getGrantId(): ?int
    {
        return $this->grantId;
    }

    public function setGrantId(?int $grantId): self
    {
        $this->grantId = $grantId;
        $this->touch();
        return $this;
    }

    public function getLockCategory(): string
    {
        return $this->lockCategory;
    }

    public function setLockCategory(string $lockCategory): self
    {
        $this->lockCategory = mb_substr(trim($lockCategory), 0, 30);
        $this->touch();
        return $this;
    }

    public function getAcquiredAt(): \DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    public function setAcquiredAt(\DateTimeImmutable $acquiredAt): self
    {
        $this->acquiredAt = $acquiredAt;
        $this->touch();
        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        $this->touch();
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        $this->touch();
        return $this;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): self
    {
        $this->releasedAt = $releasedAt;
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

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function release(): self
    {
        $now = new \DateTimeImmutable();
        $this->status = 'released';
        $this->releasedAt = $now;
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;
        return $this;
    }

    public function heartbeat(int $ttlSeconds): self
    {
        $now = new \DateTimeImmutable();
        $this->status = 'active';
        $this->lastSeenAt = $now;
        $this->expiresAt = $now->modify('+' . max(30, $ttlSeconds) . ' seconds');
        $this->updatedAt = $now;
        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
