<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserGridLayoutPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserGridLayoutPreferenceRepository::class)]
#[ORM\Table(name: 'user_grid_layout_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_grid_layout_preference', columns: ['tenant_id', 'user_id', 'screen_id', 'layout_id'])]
#[ORM\Index(name: 'idx_user_grid_layout_lookup', columns: ['tenant_id', 'user_id', 'screen_id', 'default_preference'])]
class UserGridLayoutPreference
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
    private string $layoutId = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column]
    private bool $defaultPreference = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $definitionHash = null;

    #[ORM\Column(type: Types::JSON)]
    private array $columnsOrder = [];

    #[ORM\Column(type: Types::JSON)]
    private array $hiddenColumns = [];

    #[ORM\Column(type: Types::JSON)]
    private array $columnWidths = [];

    #[ORM\Column(type: Types::JSON)]
    private array $frozenColumns = [];

    #[ORM\Column(type: Types::JSON)]
    private array $addedColumns = [];

    #[ORM\Column(type: Types::JSON)]
    private array $sortConfig = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $filterConfig = null;

    #[ORM\Column(type: Types::JSON)]
    private array $groupConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $groupAggregates = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $mobileTemplate = null;

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

    public function getLayoutId(): string
    {
        return $this->layoutId;
    }

    public function setLayoutId(string $layoutId): self
    {
        $this->layoutId = mb_substr(trim($layoutId), 0, 80);
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

    public function getDefinitionHash(): ?string
    {
        return $this->definitionHash;
    }

    public function setDefinitionHash(?string $definitionHash): self
    {
        $this->definitionHash = $definitionHash === null ? null : mb_substr(trim($definitionHash), 0, 120);
        $this->touch();
        return $this;
    }

    public function getColumnsOrder(): array
    {
        return $this->columnsOrder;
    }

    public function setColumnsOrder(array $columnsOrder): self
    {
        $this->columnsOrder = $columnsOrder;
        $this->touch();
        return $this;
    }

    public function getHiddenColumns(): array
    {
        return $this->hiddenColumns;
    }

    public function setHiddenColumns(array $hiddenColumns): self
    {
        $this->hiddenColumns = $hiddenColumns;
        $this->touch();
        return $this;
    }

    public function getColumnWidths(): array
    {
        return $this->columnWidths;
    }

    public function setColumnWidths(array $columnWidths): self
    {
        $this->columnWidths = $columnWidths;
        $this->touch();
        return $this;
    }

    public function getFrozenColumns(): array
    {
        return $this->frozenColumns;
    }

    public function setFrozenColumns(array $frozenColumns): self
    {
        $this->frozenColumns = $frozenColumns;
        $this->touch();
        return $this;
    }

    public function getAddedColumns(): array
    {
        return $this->addedColumns;
    }

    public function setAddedColumns(array $addedColumns): self
    {
        $this->addedColumns = $addedColumns;
        $this->touch();
        return $this;
    }

    public function getSortConfig(): array
    {
        return $this->sortConfig;
    }

    public function setSortConfig(array $sortConfig): self
    {
        $this->sortConfig = $sortConfig;
        $this->touch();
        return $this;
    }

    public function getFilterConfig(): ?array
    {
        return $this->filterConfig;
    }

    public function setFilterConfig(?array $filterConfig): self
    {
        $this->filterConfig = $filterConfig;
        $this->touch();
        return $this;
    }

    public function getGroupConfig(): array
    {
        return $this->groupConfig;
    }

    public function setGroupConfig(array $groupConfig): self
    {
        $this->groupConfig = $groupConfig;
        $this->touch();
        return $this;
    }

    public function getGroupAggregates(): array
    {
        return $this->groupAggregates;
    }

    public function setGroupAggregates(array $groupAggregates): self
    {
        $this->groupAggregates = $groupAggregates;
        $this->touch();
        return $this;
    }

    public function getMobileTemplate(): ?array
    {
        return $this->mobileTemplate;
    }

    public function setMobileTemplate(?array $mobileTemplate): self
    {
        $this->mobileTemplate = $mobileTemplate;
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
