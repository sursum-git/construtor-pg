<?php

namespace App\Entity;

use App\Repository\ProgramChangeRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramChangeRequestRepository::class)]
#[ORM\Table(name: 'program_change_request')]
#[ORM\UniqueConstraint(name: 'uniq_program_change_request_code', columns: ['request_code'])]
#[ORM\Index(name: 'idx_program_change_request_program', columns: ['program_code', 'status', 'updated_at'])]
class ProgramChangeRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $requestCode = '';

    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $builderEntityCode = null;

    #[ORM\Column(length: 120)]
    private string $requestedBy = '';

    #[ORM\Column(type: Types::JSON)]
    private array $requestedActions = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

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

    public function getRequestCode(): string
    {
        return $this->requestCode;
    }

    public function setRequestCode(string $requestCode): self
    {
        $this->requestCode = mb_substr(trim($requestCode), 0, 120);
        $this->touch();
        return $this;
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

    public function getBuilderEntityCode(): ?string
    {
        return $this->builderEntityCode;
    }

    public function setBuilderEntityCode(?string $builderEntityCode): self
    {
        $this->builderEntityCode = $builderEntityCode === null || $builderEntityCode === '' ? null : mb_substr(trim($builderEntityCode), 0, 120);
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

    public function getRequestedActions(): array
    {
        return $this->requestedActions;
    }

    public function setRequestedActions(array $requestedActions): self
    {
        $this->requestedActions = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $requestedActions)));
        $this->touch();
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason === null || $reason === '' ? null : $reason;
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
