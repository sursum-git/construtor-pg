<?php

namespace App\Entity;

use App\Repository\AuthLoginChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthLoginChallengeRepository::class)]
#[ORM\Table(name: 'auth_login_challenge')]
#[ORM\UniqueConstraint(name: 'uniq_auth_login_challenge_hash', columns: ['token_hash'])]
#[ORM\Index(columns: ['status', 'expires_at'], name: 'idx_auth_login_challenge_status')]
class AuthLoginChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $tokenHash = '';

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column(type: Types::JSON)]
    private array $userPayload = [];

    #[ORM\Column(length: 80)]
    private string $providerCode = '';

    #[ORM\Column(length: 40)]
    private string $providerType = '';

    #[ORM\Column]
    private bool $remember = false;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $defaultSubscriberCode = null;

    #[ORM\Column(type: Types::JSON)]
    private array $availableSubscribers = [];

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->expiresAt = $now->modify('+10 minutes');
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserPayload(): array
    {
        return $this->userPayload;
    }

    public function setUserPayload(array $userPayload): self
    {
        $this->userPayload = $userPayload;
        $this->touch();
        return $this;
    }

    public function getProviderCode(): string
    {
        return $this->providerCode;
    }

    public function setProviderCode(string $providerCode): self
    {
        $this->providerCode = mb_substr($providerCode, 0, 80);
        $this->touch();
        return $this;
    }

    public function getProviderType(): string
    {
        return $this->providerType;
    }

    public function setProviderType(string $providerType): self
    {
        $this->providerType = mb_substr($providerType, 0, 40);
        $this->touch();
        return $this;
    }

    public function shouldRemember(): bool
    {
        return $this->remember;
    }

    public function setRemember(bool $remember): self
    {
        $this->remember = $remember;
        $this->touch();
        return $this;
    }

    public function getDefaultSubscriberCode(): ?string
    {
        return $this->defaultSubscriberCode;
    }

    public function setDefaultSubscriberCode(?string $defaultSubscriberCode): self
    {
        $this->defaultSubscriberCode = $defaultSubscriberCode === null || $defaultSubscriberCode === ''
            ? null
            : mb_substr($defaultSubscriberCode, 0, 80);
        $this->touch();
        return $this;
    }

    public function getAvailableSubscribers(): array
    {
        return $this->availableSubscribers;
    }

    public function setAvailableSubscribers(array $availableSubscribers): self
    {
        $this->availableSubscribers = $availableSubscribers;
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
