<?php

namespace App\Entity;

use App\Repository\ImportExportExecutionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportExportExecutionRepository::class)]
#[ORM\Table(name: 'import_export_execution')]
class ImportExportExecution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mapping_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ImportExportMapping $mapping = null;

    #[ORM\Column(length: 120)]
    private string $mappingCode = '';

    #[ORM\Column(length: 160)]
    private string $mappingName = '';

    #[ORM\Column(length: 20)]
    private string $direction = 'export';

    #[ORM\Column(length: 40)]
    private string $format = 'entity_copy';

    #[ORM\Column(length: 20)]
    private string $mode = 'execute';

    #[ORM\Column(length: 20)]
    private string $status = 'succeeded';

    #[ORM\Column(type: 'json')]
    private array $parameters = [];

    #[ORM\Column(type: 'json')]
    private array $counts = [];

    #[ORM\Column(type: 'json')]
    private array $diagnostics = [];

    #[ORM\Column(type: 'json')]
    private array $resultSummary = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $scheduleCode = null;

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

    public function getMappingCode(): string
    {
        return $this->mappingCode;
    }

    public function setMappingCode(string $mappingCode): self
    {
        $this->mappingCode = $mappingCode;
        return $this;
    }

    public function getMappingName(): string
    {
        return $this->mappingName;
    }

    public function setMappingName(string $mappingName): self
    {
        $this->mappingName = $mappingName;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getCounts(): array
    {
        return $this->counts;
    }

    public function setCounts(array $counts): self
    {
        $this->counts = $counts;
        return $this;
    }

    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function setDiagnostics(array $diagnostics): self
    {
        $this->diagnostics = $diagnostics;
        return $this;
    }

    public function getResultSummary(): array
    {
        return $this->resultSummary;
    }

    public function setResultSummary(array $resultSummary): self
    {
        $this->resultSummary = $resultSummary;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = $durationMs;
        return $this;
    }

    public function getScheduleCode(): ?string
    {
        return $this->scheduleCode;
    }

    public function setScheduleCode(?string $scheduleCode): self
    {
        $this->scheduleCode = $scheduleCode;
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
