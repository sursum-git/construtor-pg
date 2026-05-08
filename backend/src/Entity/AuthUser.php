<?php

namespace App\Entity;

use App\Repository\AuthUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthUserRepository::class)]
#[ORM\Table(name: 'auth_user')]
#[ORM\UniqueConstraint(name: 'uniq_auth_user_tenant_username', columns: ['tenant_id', 'normalized_username'])]
#[ORM\Index(columns: ['tenant_id', 'status'], name: 'idx_auth_user_status')]
class AuthUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 160)]
    private string $username = '';

    #[ORM\Column(length: 160)]
    private string $normalizedUsername = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column(type: Types::JSON)]
    private array $groups = [];

    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    #[ORM\Column(length: 40)]
    private string $authSource = 'local';

    #[ORM\Column]
    private bool $forcePasswordChange = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

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

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr($tenantId, 0, 80);
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = mb_substr(trim($username), 0, 160);
        $this->normalizedUsername = self::normalizeUsername($this->username);
        $this->touch();
        return $this;
    }

    public function getNormalizedUsername(): string
    {
        return $this->normalizedUsername;
    }

    public static function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName === null || $displayName === '' ? null : mb_substr($displayName, 0, 160);
        $this->touch();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email === null || $email === '' ? null : mb_substr($email, 0, 180);
        $this->touch();
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        $this->touch();
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

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): self
    {
        $this->groups = array_values(array_filter(array_map('strval', $groups)));
        $this->touch();
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        $this->touch();
        return $this;
    }

    public function getAuthSource(): string
    {
        return $this->authSource;
    }

    public function setAuthSource(string $authSource): self
    {
        $this->authSource = mb_substr($authSource, 0, 40);
        $this->touch();
        return $this;
    }

    public function mustChangePassword(): bool
    {
        return $this->forcePasswordChange;
    }

    public function setForcePasswordChange(bool $forcePasswordChange): self
    {
        $this->forcePasswordChange = $forcePasswordChange;
        $this->touch();
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markLogin(): self
    {
        $this->lastLoginAt = new \DateTimeImmutable();
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
