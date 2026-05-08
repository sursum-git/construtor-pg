<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderEntitySituationTransitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderEntitySituationTransitionRepository::class)]
#[ORM\Table(name: 'builder_entity_situation_transition')]
#[ORM\Index(name: 'idx_builder_entity_situation_transition_entity', columns: ['builder_entity_id'])]
#[ORM\Index(name: 'idx_builder_entity_situation_transition_lookup', columns: ['builder_entity_id', 'from_code', 'action_id'])]
#[ORM\UniqueConstraint(name: 'uniq_builder_entity_situation_transition', columns: ['builder_entity_id', 'from_code', 'to_code', 'action_id'])]
class BuilderEntitySituationTransition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'situationTransitions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuilderEntity $builderEntity = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $fromCode = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[ORM\Column(length: 80)]
    private string $toCode = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120)]
    private string $actionId = 'update';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $permission = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::JSON)]
    private array $guardConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $effects = [];

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

    public function getBuilderEntity(): ?BuilderEntity
    {
        return $this->builderEntity;
    }

    public function setBuilderEntity(?BuilderEntity $builderEntity): self
    {
        $this->builderEntity = $builderEntity;
        $this->touch();
        return $this;
    }

    public function getFromCode(): ?string
    {
        return $this->fromCode;
    }

    public function setFromCode(?string $fromCode): self
    {
        $this->fromCode = $fromCode === null || $fromCode === '' ? null : mb_substr(trim($fromCode), 0, 80);
        $this->touch();
        return $this;
    }

    public function getToCode(): string
    {
        return $this->toCode;
    }

    public function setToCode(string $toCode): self
    {
        $this->toCode = mb_substr(trim($toCode), 0, 80);
        $this->touch();
        return $this;
    }

    public function getActionId(): string
    {
        return $this->actionId;
    }

    public function setActionId(string $actionId): self
    {
        $this->actionId = mb_substr(trim($actionId), 0, 120);
        $this->touch();
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label === null || $label === '' ? null : mb_substr(trim($label), 0, 160);
        $this->touch();
        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function setPermission(?string $permission): self
    {
        $this->permission = $permission === null || $permission === '' ? null : mb_substr(trim($permission), 0, 160);
        $this->touch();
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        $this->touch();
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();
        return $this;
    }

    public function getGuardConfig(): array
    {
        return $this->guardConfig;
    }

    public function setGuardConfig(array $guardConfig): self
    {
        $this->guardConfig = $guardConfig;
        $this->touch();
        return $this;
    }

    public function getEffects(): array
    {
        return $this->effects;
    }

    public function setEffects(array $effects): self
    {
        $this->effects = $effects;
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
