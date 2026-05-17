<?php

namespace App\Entity;

use App\Repository\ProgramGovernanceRetentionRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramGovernanceRetentionRunRepository::class)]
#[ORM\Table(name: 'program_governance_retention_run')]
#[ORM\Index(name: 'idx_program_governance_retention_run_created', columns: ['created_at', 'mode'])]
class ProgramGovernanceRetentionRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $mode = 'preview';

    #[ORM\Column(length: 20)]
    private string $source = 'ui';

    #[ORM\Column(length: 64)]
    private string $executionGroup = '';

    #[ORM\Column(nullable: true)]
    private ?int $relatedRunId = null;

    #[ORM\Column(length: 120)]
    private string $executedBy = 'system';

    #[ORM\Column(length: 40)]
    private string $databaseEnvironment = 'dev';

    #[ORM\Column(length: 120)]
    private string $databaseIdentity = 'db:dev';

    #[ORM\Column]
    private int $totalRecords = 0;

    #[ORM\Column(type: Types::JSON)]
    private array $report = [];

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

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = mb_substr(trim($mode), 0, 20) ?: 'preview';
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = mb_substr(trim($source), 0, 20) ?: 'ui';
        return $this;
    }

    public function getExecutionGroup(): string
    {
        return $this->executionGroup;
    }

    public function setExecutionGroup(string $executionGroup): self
    {
        $this->executionGroup = mb_substr(trim($executionGroup), 0, 64) ?: ('ret-' . $this->createdAt->format('YmdHis'));
        return $this;
    }

    public function getRelatedRunId(): ?int
    {
        return $this->relatedRunId;
    }

    public function setRelatedRunId(?int $relatedRunId): self
    {
        $this->relatedRunId = $relatedRunId && $relatedRunId > 0 ? $relatedRunId : null;
        return $this;
    }

    public function getExecutedBy(): string
    {
        return $this->executedBy;
    }

    public function setExecutedBy(string $executedBy): self
    {
        $this->executedBy = mb_substr(trim($executedBy), 0, 120) ?: 'system';
        return $this;
    }

    public function getDatabaseEnvironment(): string
    {
        return $this->databaseEnvironment;
    }

    public function setDatabaseEnvironment(string $databaseEnvironment): self
    {
        $this->databaseEnvironment = mb_substr(trim($databaseEnvironment), 0, 40) ?: 'dev';
        return $this;
    }

    public function getDatabaseIdentity(): string
    {
        return $this->databaseIdentity;
    }

    public function setDatabaseIdentity(string $databaseIdentity): self
    {
        $this->databaseIdentity = mb_substr(trim($databaseIdentity), 0, 120) ?: 'db:dev';
        return $this;
    }

    public function getTotalRecords(): int
    {
        return $this->totalRecords;
    }

    public function setTotalRecords(int $totalRecords): self
    {
        $this->totalRecords = max(0, $totalRecords);
        return $this;
    }

    public function getReport(): array
    {
        return $this->report;
    }

    public function setReport(array $report): self
    {
        $this->report = $report;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
