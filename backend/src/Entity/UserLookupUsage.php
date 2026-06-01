<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserLookupUsageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserLookupUsageRepository::class)]
#[ORM\Table(name: 'user_lookup_usage')]
#[ORM\UniqueConstraint(name: 'uniq_user_lookup_usage', columns: ['tenant_id', 'user_id', 'screen_id', 'filter_id', 'lookup_value'])]
#[ORM\Index(name: 'idx_user_lookup_usage_lookup', columns: ['tenant_id', 'user_id', 'screen_id', 'filter_id', 'hits', 'last_used_at'])]
class UserLookupUsage
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

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fieldName = null;

    #[ORM\Column(length: 160)]
    private string $lookupValue = '';

    #[ORM\Column(length: 255)]
    private string $lookupText = '';

    #[ORM\Column]
    private int $hits = 0;

    #[ORM\Column]
    private \DateTimeImmutable $lastUsedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->lastUsedAt = $now;
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

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(?string $fieldName): self
    {
        $value = trim((string) $fieldName);
        $this->fieldName = $value !== '' ? mb_substr($value, 0, 120) : null;
        $this->touch();
        return $this;
    }

    public function getLookupValue(): string
    {
        return $this->lookupValue;
    }

    public function setLookupValue(string $lookupValue): self
    {
        $this->lookupValue = mb_substr(trim($lookupValue), 0, 160);
        $this->touch();
        return $this;
    }

    public function getLookupText(): string
    {
        return $this->lookupText;
    }

    public function setLookupText(string $lookupText): self
    {
        $this->lookupText = mb_substr(trim($lookupText), 0, 255);
        $this->touch();
        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function setHits(int $hits): self
    {
        $this->hits = max(0, $hits);
        $this->touch();
        return $this;
    }

    public function incrementHits(): self
    {
        $this->hits += 1;
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function getLastUsedAt(): \DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
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
