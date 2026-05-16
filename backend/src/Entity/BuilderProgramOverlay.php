<?php

namespace App\Entity;

use App\Repository\BuilderProgramOverlayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuilderProgramOverlayRepository::class)]
#[ORM\Table(name: 'builder_program_overlay')]
#[ORM\UniqueConstraint(name: 'uniq_builder_program_overlay_identity', columns: ['program_code', 'subscriber_id', 'customization_kind'])]
#[ORM\Index(name: 'idx_builder_program_overlay_status', columns: ['program_code', 'subscriber_id', 'status', 'updated_at'])]
class BuilderProgramOverlay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[ORM\Column(length: 120)]
    private string $subscriberId = '';

    #[ORM\Column(length: 30)]
    private string $customizationKind = 'customer_overlay';

    #[ORM\Column(nullable: true)]
    private ?int $baseProgramVersionId = null;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column]
    private bool $upgradeFrozen = false;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $frozenReason = null;

    #[ORM\Column(type: Types::JSON)]
    private array $overlayConfig = [];

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

    public function getProgramCode(): string
    {
        return $this->programCode;
    }

    public function setProgramCode(string $programCode): self
    {
        $this->programCode = mb_substr(trim($programCode), 0, 120);
        $this->touch();
        return $this;
    }

    public function getSubscriberId(): string
    {
        return $this->subscriberId;
    }

    public function setSubscriberId(string $subscriberId): self
    {
        $this->subscriberId = mb_substr(trim($subscriberId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getCustomizationKind(): string
    {
        return $this->customizationKind;
    }

    public function setCustomizationKind(string $customizationKind): self
    {
        $this->customizationKind = mb_substr(trim($customizationKind), 0, 30);
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20);
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
        $this->frozenReason = $frozenReason === null || $frozenReason === '' ? null : mb_substr(trim($frozenReason), 0, 160);
        $this->touch();
        return $this;
    }

    public function getOverlayConfig(): array
    {
        return $this->overlayConfig;
    }

    public function setOverlayConfig(array $overlayConfig): self
    {
        $this->overlayConfig = $overlayConfig;
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
