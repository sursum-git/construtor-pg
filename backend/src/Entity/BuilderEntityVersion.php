<?php

namespace App\Entity;

use App\Repository\BuilderEntityVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BuilderEntityVersionRepository::class)]
#[ORM\Table(name: 'builder_entity_version')]
#[ORM\Index(name: 'idx_builder_entity_version_entity', columns: ['builder_entity_code', 'status', 'updated_at'])]
#[ORM\UniqueConstraint(name: 'uniq_builder_entity_version_revision', columns: ['builder_entity_code', 'revision'])]
class BuilderEntityVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $builderEntityCode = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $entityName = '';

    #[ORM\Column(length: 30)]
    private string $entityType = 'persistence';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $tableName = null;

    #[ORM\Column]
    private int $revision = 1;

    #[ORM\Column(length: 20)]
    private string $status = 'current';

    #[ORM\Column(length: 20)]
    private string $action = 'save';

    #[ORM\Column(nullable: true)]
    private ?int $sourceVersionId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $snapshot = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeSummary = null;

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

    public function getBuilderEntityCode(): string
    {
        return $this->builderEntityCode;
    }

    public function setBuilderEntityCode(string $builderEntityCode): self
    {
        $this->builderEntityCode = mb_substr($builderEntityCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = mb_substr($entityName, 0, 160);
        $this->touch();
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = mb_substr($entityType, 0, 30);
        $this->touch();
        return $this;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function setTableName(?string $tableName): self
    {
        $this->tableName = $tableName === null || $tableName === '' ? null : mb_substr($tableName, 0, 160);
        $this->touch();
        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): self
    {
        $this->revision = max(1, $revision);
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr($status, 0, 20);
        $this->touch();
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = mb_substr($action, 0, 20);
        $this->touch();
        return $this;
    }

    public function getSourceVersionId(): ?int
    {
        return $this->sourceVersionId;
    }

    public function setSourceVersionId(?int $sourceVersionId): self
    {
        $this->sourceVersionId = $sourceVersionId;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
