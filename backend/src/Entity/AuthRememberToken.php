<?php

namespace App\Entity;

use App\Repository\AuthRememberTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthRememberTokenRepository::class)]
#[ORM\Table(name: 'auth_remember_token')]
#[ORM\UniqueConstraint(name: 'uniq_auth_remember_token_hash', columns: ['token_hash'])]
#[ORM\Index(columns: ['tenant_id', 'user_id', 'status'], name: 'idx_auth_remember_token_user')]
#[ORM\Index(columns: ['expires_at', 'status'], name: 'idx_auth_remember_token_expiration')]
class AuthRememberToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = '';

    #[ORM\Column(length: 160)]
    private string $username = '';

    #[ORM\Column(length: 120)]
    private string $tokenHash = '';

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $revokeReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->expiresAt = $now->modify('+30 days');
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = mb_substr($username, 0, 160);
        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = mb_substr($tokenHash, 0, 120);
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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
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

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokeReason(): ?string
    {
        return $this->revokeReason;
    }

    public function revoke(string $reason): self
    {
        $this->status = 'revoked';
        $this->revokedAt = new \DateTimeImmutable();
        $this->revokeReason = mb_substr($reason, 0, 255);
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
