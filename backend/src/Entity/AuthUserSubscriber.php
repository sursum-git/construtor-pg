<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\AuthUserSubscriberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: AuthUserSubscriberRepository::class)]
#[ORM\Table(name: 'auth_user_subscriber')]
#[ORM\UniqueConstraint(name: 'uniq_auth_user_subscriber', columns: ['user_tenant_id', 'username', 'subscriber_code'])]
#[ORM\Index(columns: ['user_tenant_id', 'username', 'enabled'], name: 'idx_auth_user_subscriber_user')]
#[ORM\Index(columns: ['subscriber_code', 'enabled'], name: 'idx_auth_user_subscriber_subscriber')]
class AuthUserSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $userTenantId = 'default';

    #[ORM\Column(length: 160)]
    private string $username = '';

    #[ORM\Column(length: 80)]
    private string $subscriberCode = '';

    #[ORM\Column]
    private bool $defaultSubscriber = false;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::JSON)]
    private array $permissionOverrides = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

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

    public function getUserTenantId(): string
    {
        return $this->userTenantId;
    }

    public function setUserTenantId(string $userTenantId): self
    {
        $this->userTenantId = mb_substr(trim($userTenantId), 0, 80) ?: 'default';
        $this->touch();
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = mb_substr(mb_strtolower(trim($username)), 0, 160);
        $this->touch();
        return $this;
    }

    public function getSubscriberCode(): string
    {
        return $this->subscriberCode;
    }

    public function setSubscriberCode(string $subscriberCode): self
    {
        $this->subscriberCode = mb_substr(trim($subscriberCode), 0, 80);
        $this->touch();
        return $this;
    }

    public function isDefaultSubscriber(): bool
    {
        return $this->defaultSubscriber;
    }

    public function setDefaultSubscriber(bool $defaultSubscriber): self
    {
        $this->defaultSubscriber = $defaultSubscriber;
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

    public function getPermissionOverrides(): array
    {
        return $this->permissionOverrides;
    }

    public function setPermissionOverrides(array $permissionOverrides): self
    {
        $this->permissionOverrides = $permissionOverrides;
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
