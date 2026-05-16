<?php

namespace App\Entity;

use App\Repository\ProgramChangeGrantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramChangeGrantRepository::class)]
#[ORM\Table(name: 'program_change_grant')]
#[ORM\Index(name: 'idx_program_change_grant_program', columns: ['program_code', 'granted_to_user_id', 'status', 'updated_at'])]
class ProgramChangeGrant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProgramChangeRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProgramChangeRequest $request;

    #[ORM\Column(length: 120)]
    private string $programCode = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $builderEntityCode = null;

    #[ORM\Column(length: 120)]
    private string $grantedToUserId = '';

    #[ORM\Column(type: Types::JSON)]
    private array $allowedActions = [];

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column]
    private bool $validUntilPublish = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

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

    public function getRequest(): ProgramChangeRequest
    {
        return $this->request;
    }

    public function setRequest(ProgramChangeRequest $request): self
    {
        $this->request = $request;
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

    public function getGrantedToUserId(): string
    {
        return $this->grantedToUserId;
    }

    public function setGrantedToUserId(string $grantedToUserId): self
    {
        $this->grantedToUserId = mb_substr(trim($grantedToUserId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getAllowedActions(): array
    {
        return $this->allowedActions;
    }

    public function setAllowedActions(array $allowedActions): self
    {
        $this->allowedActions = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $allowedActions)));
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

    public function isValidUntilPublish(): bool
    {
        return $this->validUntilPublish;
    }

    public function setValidUntilPublish(bool $validUntilPublish): self
    {
        $this->validUntilPublish = $validUntilPublish;
        $this->touch();
        return $this;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeImmutable $consumedAt): self
    {
        $this->consumedAt = $consumedAt;
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
