<?php

namespace App\Entity;

use App\Repository\SystemUpdateExecutionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemUpdateExecutionRepository::class)]
#[ORM\Table(name: 'system_update_execution')]
#[ORM\Index(name: 'idx_system_update_execution_version', columns: ['release_version', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_system_update_execution_env', columns: ['deployment_mode', 'database_identity', 'created_at'])]
#[ORM\Index(name: 'idx_system_update_execution_subscriber', columns: ['target_subscriber_code', 'created_at'])]
class SystemUpdateExecution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $releaseVersion = '';

    #[ORM\Column(length: 160)]
    private string $releaseTitle = '';

    #[ORM\Column(length: 40)]
    private string $category = 'recommended';

    #[ORM\Column(length: 30)]
    private string $severity = 'medium';

    #[ORM\Column(length: 20)]
    private string $status = 'queued';

    #[ORM\Column(length: 20)]
    private string $mode = 'manual';

    #[ORM\Column(length: 30)]
    private string $deploymentMode = 'shared';

    #[ORM\Column(length: 40)]
    private string $databaseEnvironment = 'dev';

    #[ORM\Column(length: 120)]
    private string $databaseIdentity = 'db:dev';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $targetSubscriberCode = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $targetSubscriberName = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $targetDatabaseEnvironment = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $targetDatabaseIdentity = null;

    #[ORM\Column(length: 120)]
    private string $initiatedBy = 'system';

    #[ORM\Column(length: 30)]
    private string $initiatedSource = 'ui';

    #[ORM\Column(nullable: true)]
    private ?int $runtimeJobId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $summary = [];

    #[ORM\Column(type: Types::JSON)]
    private array $impactReport = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

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

    public function getReleaseVersion(): string
    {
        return $this->releaseVersion;
    }

    public function setReleaseVersion(string $releaseVersion): self
    {
        $this->releaseVersion = mb_substr(trim($releaseVersion), 0, 40);
        return $this;
    }

    public function getReleaseTitle(): string
    {
        return $this->releaseTitle;
    }

    public function setReleaseTitle(string $releaseTitle): self
    {
        $this->releaseTitle = mb_substr(trim($releaseTitle), 0, 160);
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20) ?: 'queued';
        $this->touch();
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = mb_substr(trim($mode), 0, 20) ?: 'manual';
        return $this;
    }

    public function getDeploymentMode(): string
    {
        return $this->deploymentMode;
    }

    public function setDeploymentMode(string $deploymentMode): self
    {
        $this->deploymentMode = mb_substr(trim($deploymentMode), 0, 30) ?: 'shared';
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

    public function getTargetSubscriberCode(): ?string
    {
        return $this->targetSubscriberCode;
    }

    public function setTargetSubscriberCode(?string $targetSubscriberCode): self
    {
        $normalized = trim((string) $targetSubscriberCode);
        $this->targetSubscriberCode = $normalized !== '' ? mb_substr($normalized, 0, 120) : null;
        return $this;
    }

    public function getTargetSubscriberName(): ?string
    {
        return $this->targetSubscriberName;
    }

    public function setTargetSubscriberName(?string $targetSubscriberName): self
    {
        $normalized = trim((string) $targetSubscriberName);
        $this->targetSubscriberName = $normalized !== '' ? mb_substr($normalized, 0, 160) : null;
        return $this;
    }

    public function getTargetDatabaseEnvironment(): ?string
    {
        return $this->targetDatabaseEnvironment;
    }

    public function setTargetDatabaseEnvironment(?string $targetDatabaseEnvironment): self
    {
        $normalized = trim((string) $targetDatabaseEnvironment);
        $this->targetDatabaseEnvironment = $normalized !== '' ? mb_substr($normalized, 0, 40) : null;
        return $this;
    }

    public function getTargetDatabaseIdentity(): ?string
    {
        return $this->targetDatabaseIdentity;
    }

    public function setTargetDatabaseIdentity(?string $targetDatabaseIdentity): self
    {
        $normalized = trim((string) $targetDatabaseIdentity);
        $this->targetDatabaseIdentity = $normalized !== '' ? mb_substr($normalized, 0, 120) : null;
        return $this;
    }

    public function getInitiatedBy(): string
    {
        return $this->initiatedBy;
    }

    public function setInitiatedBy(string $initiatedBy): self
    {
        $this->initiatedBy = mb_substr(trim($initiatedBy), 0, 120) ?: 'system';
        return $this;
    }

    public function getInitiatedSource(): string
    {
        return $this->initiatedSource;
    }

    public function setInitiatedSource(string $initiatedSource): self
    {
        $this->initiatedSource = mb_substr(trim($initiatedSource), 0, 30) ?: 'ui';
        return $this;
    }

    public function getRuntimeJobId(): ?int
    {
        return $this->runtimeJobId;
    }

    public function setRuntimeJobId(?int $runtimeJobId): self
    {
        $this->runtimeJobId = $runtimeJobId && $runtimeJobId > 0 ? $runtimeJobId : null;
        return $this;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function setSummary(array $summary): self
    {
        $this->summary = $summary;
        $this->touch();
        return $this;
    }

    public function getImpactReport(): array
    {
        return $this->impactReport;
    }

    public function setImpactReport(array $impactReport): self
    {
        $this->impactReport = $impactReport;
        $this->touch();
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage !== null && trim($errorMessage) !== '' ? $errorMessage : null;
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

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        $this->touch();
        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
