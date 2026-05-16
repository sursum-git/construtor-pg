<?php

namespace App\Entity;

use App\Repository\BuilderProgramVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BuilderProgramVersionRepository::class)]
#[ORM\Table(name: 'builder_program_version')]
#[ORM\Index(name: 'idx_builder_program_version_program', columns: ['program_code', 'status', 'updated_at'])]
#[ORM\UniqueConstraint(name: 'uniq_builder_program_version_code_version', columns: ['program_code', 'version'])]
class BuilderProgramVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $programTitle = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $module = null;

    #[ORM\Column(length: 30)]
    private string $pageType = 'crud';

    #[ORM\Column(length: 120)]
    private string $builderEntityCode = '';

    #[ORM\Column(length: 160)]
    private string $screenId = '';

    #[ORM\Column(length: 40)]
    private string $version = '1.0.0';

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $permissionPrefix = null;

    #[ORM\Column]
    private bool $allowCreate = true;

    #[ORM\Column]
    private bool $allowUpdate = true;

    #[ORM\Column]
    private bool $allowDelete = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeSummary = null;

    #[ORM\Column(type: Types::JSON)]
    private array $builderConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $generatedDefinition = [];

    #[ORM\Column(length: 30)]
    private string $programOrigin = 'standard';

    #[ORM\Column(length: 20)]
    private string $ownerScope = 'system';

    #[ORM\Column(length: 30)]
    private string $customizationPolicy = 'overlay_only';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subscriberId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $baseProgramCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $baseProgramVersionId = null;

    #[ORM\Column]
    private bool $upgradeFrozen = false;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $frozenReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

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

    public function getProgramCode(): string
    {
        return $this->programCode;
    }

    public function setProgramCode(string $programCode): self
    {
        $this->programCode = mb_substr($programCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getProgramTitle(): string
    {
        return $this->programTitle;
    }

    public function setProgramTitle(string $programTitle): self
    {
        $this->programTitle = mb_substr($programTitle, 0, 160);
        $this->touch();
        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(?string $module): self
    {
        $this->module = $module === null || $module === '' ? null : mb_substr($module, 0, 120);
        $this->touch();
        return $this;
    }

    public function getPageType(): string
    {
        return $this->pageType;
    }

    public function setPageType(string $pageType): self
    {
        $this->pageType = mb_substr($pageType, 0, 30);
        $this->touch();
        return $this;
    }

    public function getBuilderEntityCode(): string
    {
        return $this->builderEntityCode;
    }

    public function setBuilderEntityCode(string $builderEntityCode): self
    {
        $this->builderEntityCode = mb_substr($builderEntityCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = mb_substr($screenId, 0, 160);
        $this->touch();
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = mb_substr($version, 0, 40);
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr($status, 0, 20);
        $this->touch();
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle === null || $subtitle === '' ? null : mb_substr($subtitle, 0, 160);
        $this->touch();
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon === null || $icon === '' ? null : mb_substr($icon, 0, 80);
        $this->touch();
        return $this;
    }

    public function getPermissionPrefix(): ?string
    {
        return $this->permissionPrefix;
    }

    public function setPermissionPrefix(?string $permissionPrefix): self
    {
        $this->permissionPrefix = $permissionPrefix === null || $permissionPrefix === '' ? null : mb_substr($permissionPrefix, 0, 160);
        $this->touch();
        return $this;
    }

    public function isAllowCreate(): bool
    {
        return $this->allowCreate;
    }

    public function setAllowCreate(bool $allowCreate): self
    {
        $this->allowCreate = $allowCreate;
        $this->touch();
        return $this;
    }

    public function isAllowUpdate(): bool
    {
        return $this->allowUpdate;
    }

    public function setAllowUpdate(bool $allowUpdate): self
    {
        $this->allowUpdate = $allowUpdate;
        $this->touch();
        return $this;
    }

    public function isAllowDelete(): bool
    {
        return $this->allowDelete;
    }

    public function setAllowDelete(bool $allowDelete): self
    {
        $this->allowDelete = $allowDelete;
        $this->touch();
        return $this;
    }

    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function setChangeSummary(?string $changeSummary): self
    {
        $this->changeSummary = $changeSummary === null || $changeSummary === '' ? null : $changeSummary;
        $this->touch();
        return $this;
    }

    public function getBuilderConfig(): array
    {
        return $this->builderConfig;
    }

    public function setBuilderConfig(array $builderConfig): self
    {
        $this->builderConfig = $builderConfig;
        $this->touch();
        return $this;
    }

    public function getGeneratedDefinition(): array
    {
        return $this->generatedDefinition;
    }

    public function setGeneratedDefinition(array $generatedDefinition): self
    {
        $this->generatedDefinition = $generatedDefinition;
        $this->touch();
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        $this->touch();
        return $this;
    }

    public function getProgramOrigin(): string
    {
        return $this->programOrigin;
    }

    public function setProgramOrigin(string $programOrigin): self
    {
        $this->programOrigin = mb_substr($programOrigin, 0, 30);
        $this->touch();
        return $this;
    }

    public function getOwnerScope(): string
    {
        return $this->ownerScope;
    }

    public function setOwnerScope(string $ownerScope): self
    {
        $this->ownerScope = mb_substr($ownerScope, 0, 20);
        $this->touch();
        return $this;
    }

    public function getCustomizationPolicy(): string
    {
        return $this->customizationPolicy;
    }

    public function setCustomizationPolicy(string $customizationPolicy): self
    {
        $this->customizationPolicy = mb_substr($customizationPolicy, 0, 30);
        $this->touch();
        return $this;
    }

    public function getSubscriberId(): ?string
    {
        return $this->subscriberId;
    }

    public function setSubscriberId(?string $subscriberId): self
    {
        $this->subscriberId = $subscriberId === null || $subscriberId === '' ? null : mb_substr($subscriberId, 0, 120);
        $this->touch();
        return $this;
    }

    public function getBaseProgramCode(): ?string
    {
        return $this->baseProgramCode;
    }

    public function setBaseProgramCode(?string $baseProgramCode): self
    {
        $this->baseProgramCode = $baseProgramCode === null || $baseProgramCode === '' ? null : mb_substr($baseProgramCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getBaseProgramVersionId(): ?int
    {
        return $this->baseProgramVersionId;
    }

    public function setBaseProgramVersionId(?int $baseProgramVersionId): self
    {
        $this->baseProgramVersionId = $baseProgramVersionId;
        $this->touch();
        return $this;
    }

    public function isUpgradeFrozen(): bool
    {
        return $this->upgradeFrozen;
    }

    public function setUpgradeFrozen(bool $upgradeFrozen): self
    {
        $this->upgradeFrozen = $upgradeFrozen;
        $this->touch();
        return $this;
    }

    public function getFrozenReason(): ?string
    {
        return $this->frozenReason;
    }

    public function setFrozenReason(?string $frozenReason): self
    {
        $this->frozenReason = $frozenReason === null || $frozenReason === '' ? null : mb_substr($frozenReason, 0, 160);
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
