<?php

namespace App\Entity;

use App\Repository\ProgramTestExecutionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramTestExecutionRepository::class)]
#[ORM\Table(name: 'program_test_execution')]
#[ORM\Index(name: 'idx_program_test_execution_bundle', columns: ['program_code', 'builder_program_version_id', 'bundle_id', 'status'])]
class ProgramTestExecution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[ORM\Column(nullable: true)]
    private ?int $builderProgramVersionId = null;

    #[ORM\Column(nullable: true)]
    private ?int $builderEntityVersionId = null;

    #[ORM\Column(length: 120)]
    private string $bundleId = '';

    #[ORM\Column(length: 160)]
    private string $testPlanId = '';

    #[ORM\Column(length: 120)]
    private string $executedBy = '';

    #[ORM\Column(length: 20)]
    private string $status = 'passed';

    #[ORM\Column(type: Types::JSON)]
    private array $checklistSnapshot = [];

    #[ORM\Column(type: Types::JSON)]
    private array $evidences = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $executedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->executedAt = $now;
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

    public function getBuilderProgramVersionId(): ?int
    {
        return $this->builderProgramVersionId;
    }

    public function setBuilderProgramVersionId(?int $builderProgramVersionId): self
    {
        $this->builderProgramVersionId = $builderProgramVersionId;
        $this->touch();
        return $this;
    }

    public function getBuilderEntityVersionId(): ?int
    {
        return $this->builderEntityVersionId;
    }

    public function setBuilderEntityVersionId(?int $builderEntityVersionId): self
    {
        $this->builderEntityVersionId = $builderEntityVersionId;
        $this->touch();
        return $this;
    }

    public function getBundleId(): string
    {
        return $this->bundleId;
    }

    public function setBundleId(string $bundleId): self
    {
        $this->bundleId = mb_substr(trim($bundleId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getTestPlanId(): string
    {
        return $this->testPlanId;
    }

    public function setTestPlanId(string $testPlanId): self
    {
        $this->testPlanId = mb_substr(trim($testPlanId), 0, 160);
        $this->touch();
        return $this;
    }

    public function getExecutedBy(): string
    {
        return $this->executedBy;
    }

    public function setExecutedBy(string $executedBy): self
    {
        $this->executedBy = mb_substr(trim($executedBy), 0, 120);
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

    public function getChecklistSnapshot(): array
    {
        return $this->checklistSnapshot;
    }

    public function setChecklistSnapshot(array $checklistSnapshot): self
    {
        $this->checklistSnapshot = $checklistSnapshot;
        $this->touch();
        return $this;
    }

    public function getEvidences(): array
    {
        return $this->evidences;
    }

    public function setEvidences(array $evidences): self
    {
        $this->evidences = $evidences;
        $this->touch();
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes === null || $notes === '' ? null : $notes;
        $this->touch();
        return $this;
    }

    public function getExecutedAt(): \DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function setExecutedAt(\DateTimeImmutable $executedAt): self
    {
        $this->executedAt = $executedAt;
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
