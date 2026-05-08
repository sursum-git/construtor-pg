<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserFilterPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserFilterPreferenceRepository::class)]
#[ORM\Table(name: 'user_filter_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_filter_preference', columns: ['tenant_id', 'user_id', 'screen_id', 'filter_id'])]
#[ORM\Index(name: 'idx_user_filter_lookup', columns: ['tenant_id', 'user_id', 'screen_id', 'default_preference'])]
class UserFilterPreference
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

    #[ORM\Column(length: 80)]
    private string $filterId = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column]
    private bool $defaultPreference = false;

    #[ORM\Column(type: Types::JSON)]
    private array $filters = [];

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

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = mb_substr(trim($screenId), 0, 160);
        $this->touch();
        return $this;
    }

    public function getFilterId(): string
    {
        return $this->filterId;
    }

    public function setFilterId(string $filterId): self
    {
        $this->filterId = mb_substr(trim($filterId), 0, 80);
        $this->touch();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr(trim($name), 0, 160);
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

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
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
