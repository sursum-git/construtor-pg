<?php

namespace App\Entity;

use App\Repository\BuilderProgramOverlayVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuilderProgramOverlayVersionRepository::class)]
#[ORM\Table(name: 'builder_program_overlay_version')]
#[ORM\Index(name: 'idx_builder_program_overlay_version_overlay', columns: ['overlay_id', 'status', 'updated_at'])]
class BuilderProgramOverlayVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BuilderProgramOverlay::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BuilderProgramOverlay $overlay;

    #[ORM\Column]
    private int $versionNumber = 1;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column(type: Types::JSON)]
    private array $snapshot = [];

    #[ORM\Column(type: Types::JSON)]
    private array $resolvedDefinition = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeSummary = null;

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

    public function getOverlay(): BuilderProgramOverlay
    {
        return $this->overlay;
    }

    public function setOverlay(BuilderProgramOverlay $overlay): self
    {
        $this->overlay = $overlay;
        $this->touch();
        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): self
    {
        $this->versionNumber = max(1, $versionNumber);
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

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): self
    {
        $this->snapshot = $snapshot;
        $this->touch();
        return $this;
    }

    public function getResolvedDefinition(): array
    {
        return $this->resolvedDefinition;
    }

    public function setResolvedDefinition(array $resolvedDefinition): self
    {
        $this->resolvedDefinition = $resolvedDefinition;
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
