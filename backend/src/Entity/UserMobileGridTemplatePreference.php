<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserMobileGridTemplatePreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserMobileGridTemplatePreferenceRepository::class)]
#[ORM\Table(name: 'user_mobile_grid_template_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_mobile_grid_template_preference', columns: ['tenant_id', 'user_id', 'screen_id', 'template_id'])]
#[ORM\Index(name: 'idx_user_mobile_grid_template_lookup', columns: ['tenant_id', 'user_id', 'screen_id', 'default_preference'])]
class UserMobileGridTemplatePreference
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
    private string $templateId = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column]
    private bool $defaultPreference = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $titleField = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subtitleField = null;

    #[ORM\Column(type: Types::JSON)]
    private array $badgeFields = [];

    #[ORM\Column(type: Types::JSON)]
    private array $fieldPositions = [];

    #[ORM\Column(type: Types::JSON)]
    private array $tabs = [];

    #[ORM\Column(type: Types::JSON)]
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

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): self
    {
        $this->templateId = mb_substr(trim($templateId), 0, 80);
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

    public function getTitleField(): ?string
    {
        return $this->titleField;
    }

    public function setTitleField(?string $titleField): self
    {
        $this->titleField = $titleField === null ? null : mb_substr(trim($titleField), 0, 120);
        $this->touch();
        return $this;
    }

    public function getSubtitleField(): ?string
    {
        return $this->subtitleField;
    }

    public function setSubtitleField(?string $subtitleField): self
    {
        $this->subtitleField = $subtitleField === null ? null : mb_substr(trim($subtitleField), 0, 120);
        $this->touch();
        return $this;
    }

    public function getBadgeFields(): array
    {
        return $this->badgeFields;
    }

    public function setBadgeFields(array $badgeFields): self
    {
        $this->badgeFields = $badgeFields;
        $this->touch();
        return $this;
    }

    public function getFieldPositions(): array
    {
        return $this->fieldPositions;
    }

    public function setFieldPositions(array $fieldPositions): self
    {
        $this->fieldPositions = $fieldPositions;
        $this->touch();
        return $this;
    }

    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function setTabs(array $tabs): self
    {
        $this->tabs = $tabs;
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

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
