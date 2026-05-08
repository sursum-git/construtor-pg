<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserLayoutPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserLayoutPreferenceRepository::class)]
#[ORM\Table(name: 'user_layout_preference')]
#[ORM\Index(name: 'idx_user_layout_lookup', columns: ['tenant_id', 'user_id', 'screen_id', 'preference_type'])]
class UserLayoutPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = 'demo';

    #[ORM\Column(length: 160)]
    private string $screenId = '';

    #[ORM\Column(length: 30)]
    private string $preferenceType = 'layout';

    #[ORM\Column(length: 80)]
    private string $preferenceId = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column]
    private bool $defaultPreference = false;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

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
        $this->tenantId = $tenantId;
        $this->touch();
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        $this->touch();
        return $this;
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = $screenId;
        $this->touch();
        return $this;
    }

    public function getPreferenceType(): string
    {
        return $this->preferenceType;
    }

    public function setPreferenceType(string $preferenceType): self
    {
        $this->preferenceType = $preferenceType;
        $this->touch();
        return $this;
    }

    public function getPreferenceId(): string
    {
        return $this->preferenceId;
    }

    public function setPreferenceId(string $preferenceId): self
    {
        $this->preferenceId = $preferenceId;
        $this->touch();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function isDefaultPreference(): bool
    {
        return $this->defaultPreference;
    }

    public function setDefaultPreference(bool $defaultPreference): self
    {
        $this->defaultPreference = $defaultPreference;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
