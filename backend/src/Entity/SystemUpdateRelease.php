<?php

namespace App\Entity;

use App\Repository\SystemUpdateReleaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemUpdateReleaseRepository::class)]
#[ORM\Table(name: 'system_update_release')]
#[ORM\UniqueConstraint(name: 'uniq_system_update_release_version', columns: ['version'])]
#[ORM\Index(name: 'idx_system_update_release_status', columns: ['category', 'severity', 'published_at'])]
class SystemUpdateRelease
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $version = '';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(length: 40)]
    private string $category = 'recommended';

    #[ORM\Column(length: 30)]
    private string $severity = 'medium';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $autoApplySaas = false;

    #[ORM\Column]
    private bool $autoApplyOnPrem = false;

    #[ORM\Column]
    private bool $requiresSubscriberConsent = true;

    #[ORM\Column]
    private bool $blocksNextUpdates = false;

    #[ORM\Column]
    private bool $internetRequired = false;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $requiresVersionMin = null;

    #[ORM\Column(type: Types::JSON)]
    private array $requiresAppliedUpdates = [];

    #[ORM\Column(type: Types::JSON)]
    private array $steps = [];

    #[ORM\Column(type: Types::JSON)]
    private array $programUpdates = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $manifestSource = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $manifestHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $checkedAt;

    public function __construct()
    {
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = mb_substr(trim($version), 0, 40);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = mb_substr(trim($title), 0, 160);
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = mb_substr(trim($category), 0, 40) ?: 'recommended';
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = mb_substr(trim($severity), 0, 30) ?: 'medium';
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $normalized = trim((string) $description);
        $this->description = $normalized !== '' ? $normalized : null;
        return $this;
    }

    public function isAutoApplySaas(): bool
    {
        return $this->autoApplySaas;
    }

    public function setAutoApplySaas(bool $autoApplySaas): self
    {
        $this->autoApplySaas = $autoApplySaas;
        return $this;
    }

    public function isAutoApplyOnPrem(): bool
    {
        return $this->autoApplyOnPrem;
    }

    public function setAutoApplyOnPrem(bool $autoApplyOnPrem): self
    {
        $this->autoApplyOnPrem = $autoApplyOnPrem;
        return $this;
    }

    public function isRequiresSubscriberConsent(): bool
    {
        return $this->requiresSubscriberConsent;
    }

    public function setRequiresSubscriberConsent(bool $requiresSubscriberConsent): self
    {
        $this->requiresSubscriberConsent = $requiresSubscriberConsent;
        return $this;
    }

    public function isBlocksNextUpdates(): bool
    {
        return $this->blocksNextUpdates;
    }

    public function setBlocksNextUpdates(bool $blocksNextUpdates): self
    {
        $this->blocksNextUpdates = $blocksNextUpdates;
        return $this;
    }

    public function isInternetRequired(): bool
    {
        return $this->internetRequired;
    }

    public function setInternetRequired(bool $internetRequired): self
    {
        $this->internetRequired = $internetRequired;
        return $this;
    }

    public function getRequiresVersionMin(): ?string
    {
        return $this->requiresVersionMin;
    }

    public function setRequiresVersionMin(?string $requiresVersionMin): self
    {
        $normalized = trim((string) $requiresVersionMin);
        $this->requiresVersionMin = $normalized !== '' ? mb_substr($normalized, 0, 40) : null;
        return $this;
    }

    public function getRequiresAppliedUpdates(): array
    {
        return $this->requiresAppliedUpdates;
    }

    public function setRequiresAppliedUpdates(array $requiresAppliedUpdates): self
    {
        $this->requiresAppliedUpdates = $requiresAppliedUpdates;
        return $this;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function setSteps(array $steps): self
    {
        $this->steps = $steps;
        return $this;
    }

    public function getProgramUpdates(): array
    {
        return $this->programUpdates;
    }

    public function setProgramUpdates(array $programUpdates): self
    {
        $this->programUpdates = $programUpdates;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getManifestSource(): ?string
    {
        return $this->manifestSource;
    }

    public function setManifestSource(?string $manifestSource): self
    {
        $normalized = trim((string) $manifestSource);
        $this->manifestSource = $normalized !== '' ? mb_substr($normalized, 0, 255) : null;
        return $this;
    }

    public function getManifestHash(): ?string
    {
        return $this->manifestHash;
    }

    public function setManifestHash(?string $manifestHash): self
    {
        $normalized = trim((string) $manifestHash);
        $this->manifestHash = $normalized !== '' ? mb_substr($normalized, 0, 64) : null;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): self
    {
        $this->checkedAt = $checkedAt;
        return $this;
    }
}
