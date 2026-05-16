<?php

namespace App\Entity;

use App\Repository\ImportExportMappingVersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportExportMappingVersionRepository::class)]
#[ORM\Table(name: 'import_export_mapping_version')]
class ImportExportMappingVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mapping_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ImportExportMapping $mapping = null;

    #[ORM\Column]
    private int $versionNumber = 1;

    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $changeSummary = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMapping(): ?ImportExportMapping
    {
        return $this->mapping;
    }

    public function setMapping(?ImportExportMapping $mapping): self
    {
        $this->mapping = $mapping;
        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): self
    {
        $this->versionNumber = $versionNumber;
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): self
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function setChangeSummary(?string $changeSummary): self
    {
        $this->changeSummary = $changeSummary;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
