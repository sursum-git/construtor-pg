<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderEntityRepository::class)]
#[ORM\Table(name: 'builder_entity')]
#[ORM\UniqueConstraint(name: 'uniq_builder_entity_code', columns: ['code'])]
class BuilderEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120)]
    private string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[ORM\Column(length: 160)]
    private string $name = '';

    #[Assert\Choice(choices: ['persistence', 'query', 'io'])]
    #[ORM\Column(length: 30)]
    private string $entityType = 'persistence';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $tableName = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $sourceName = null;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column]
    private bool $situationEnabled = false;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $situationFieldCode = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\OneToMany(mappedBy: 'builderEntity', targetEntity: BuilderField::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $fields;

    #[ORM\OneToMany(mappedBy: 'builderEntity', targetEntity: BuilderEntitySituation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $situations;

    #[ORM\OneToMany(mappedBy: 'builderEntity', targetEntity: BuilderEntitySituationTransition::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $situationTransitions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
        $this->situations = new ArrayCollection();
        $this->situationTransitions = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        $this->touch();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        $this->touch();
        return $this;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function setTableName(?string $tableName): self
    {
        $this->tableName = $tableName;
        $this->touch();
        return $this;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): self
    {
        $this->sourceName = $sourceName;
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function isSituationEnabled(): bool
    {
        return $this->situationEnabled;
    }

    public function setSituationEnabled(bool $situationEnabled): self
    {
        $this->situationEnabled = $situationEnabled;
        $this->touch();
        return $this;
    }

    public function getSituationFieldCode(): ?string
    {
        return $this->situationFieldCode;
    }

    public function setSituationFieldCode(?string $situationFieldCode): self
    {
        $this->situationFieldCode = $situationFieldCode === null || $situationFieldCode === '' ? null : mb_substr($situationFieldCode, 0, 120);
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

    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(BuilderField $field): self
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setBuilderEntity($this);
        }
        return $this;
    }

    public function removeField(BuilderField $field): self
    {
        if ($this->fields->removeElement($field) && $field->getBuilderEntity() === $this) {
            $field->setBuilderEntity(null);
        }
        return $this;
    }

    public function getSituations(): Collection
    {
        return $this->situations;
    }

    public function addSituation(BuilderEntitySituation $situation): self
    {
        if (!$this->situations->contains($situation)) {
            $this->situations->add($situation);
            $situation->setBuilderEntity($this);
        }
        return $this;
    }

    public function removeSituation(BuilderEntitySituation $situation): self
    {
        if ($this->situations->removeElement($situation) && $situation->getBuilderEntity() === $this) {
            $situation->setBuilderEntity(null);
        }
        return $this;
    }

    public function getSituationTransitions(): Collection
    {
        return $this->situationTransitions;
    }

    public function addSituationTransition(BuilderEntitySituationTransition $transition): self
    {
        if (!$this->situationTransitions->contains($transition)) {
            $this->situationTransitions->add($transition);
            $transition->setBuilderEntity($this);
        }
        return $this;
    }

    public function removeSituationTransition(BuilderEntitySituationTransition $transition): self
    {
        if ($this->situationTransitions->removeElement($transition) && $transition->getBuilderEntity() === $this) {
            $transition->setBuilderEntity(null);
        }
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
