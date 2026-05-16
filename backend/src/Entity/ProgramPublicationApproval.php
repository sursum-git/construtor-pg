<?php

namespace App\Entity;

use App\Repository\ProgramPublicationApprovalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramPublicationApprovalRepository::class)]
#[ORM\Table(name: 'program_publication_approval')]
#[ORM\Index(name: 'idx_program_publication_approval_program', columns: ['program_code', 'builder_program_version_id', 'status', 'updated_at'])]
class ProgramPublicationApproval
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[ORM\Column(nullable: true)]
    private ?int $builderProgramVersionId = null;

    #[ORM\Column(length: 120)]
    private string $requestedBy = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $testExecutionBundleId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'json')]
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

    public function getRequestedBy(): string
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(string $requestedBy): self
    {
        $this->requestedBy = mb_substr(trim($requestedBy), 0, 120);
        $this->touch();
        return $this;
    }

    public function getApprovedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?string $approvedBy): self
    {
        $this->approvedBy = $approvedBy === null || $approvedBy === '' ? null : mb_substr(trim($approvedBy), 0, 120);
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

    public function getTestExecutionBundleId(): ?string
    {
        return $this->testExecutionBundleId;
    }

    public function setTestExecutionBundleId(?string $testExecutionBundleId): self
    {
        $this->testExecutionBundleId = $testExecutionBundleId === null || $testExecutionBundleId === '' ? null : mb_substr(trim($testExecutionBundleId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
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
