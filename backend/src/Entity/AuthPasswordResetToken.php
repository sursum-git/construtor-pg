<?php

namespace App\Entity;

use App\Repository\AuthPasswordResetTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthPasswordResetTokenRepository::class)]
#[ORM\Table(name: 'auth_password_reset_token')]
#[ORM\UniqueConstraint(name: 'uniq_auth_password_reset_token_hash', columns: ['token_hash'])]
#[ORM\Index(columns: ['user_tenant_id', 'username', 'status'], name: 'idx_auth_password_reset_user')]
#[ORM\Index(columns: ['status', 'expires_at'], name: 'idx_auth_password_reset_expiration')]
class AuthPasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $userTenantId = 'default';

    #[ORM\Column(length: 160)]
    private string $username = '';

    #[ORM\Column(length: 120)]
    private string $tokenHash = '';

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->expiresAt = $now->modify('+30 minutes');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserTenantId(): string
    {
        return $this->userTenantId;
    }

    public function setUserTenantId(string $userTenantId): self
    {
        $this->userTenantId = mb_substr(trim($userTenantId), 0, 80) ?: 'default';
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = mb_substr(mb_strtolower(trim($username)), 0, 160);
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

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function markUsed(): self
    {
        $this->status = 'used';
        $this->usedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function revoke(): self
    {
        $this->status = 'revoked';
        $this->revokedAt = new \DateTimeImmutable();
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
