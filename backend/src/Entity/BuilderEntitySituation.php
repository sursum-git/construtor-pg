<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderEntitySituationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderEntitySituationRepository::class)]
#[ORM\Table(name: 'builder_entity_situation')]
#[ORM\Index(name: 'idx_builder_entity_situation_entity', columns: ['builder_entity_id'])]
#[ORM\UniqueConstraint(name: 'uniq_builder_entity_situation_code', columns: ['builder_entity_id', 'code'])]
class BuilderEntitySituation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'situations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuilderEntity $builderEntity = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[ORM\Column(length: 80)]
    private string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[ORM\Column(length: 160)]
    private string $label = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(name: 'is_initial')]
    private bool $initial = false;

    #[ORM\Column(name: 'is_final')]
    private bool $final = false;

    #[ORM\Column]
    private bool $enabled = true;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = mb_substr(trim($code), 0, 80);
        $this->touch();
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = mb_substr(trim($label), 0, 160);
        $this->touch();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description === null || $description === '' ? null : $description;
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

    public function isInitial(): bool
    {
        return $this->initial;
    }

    public function setInitial(bool $initial): self
    {
        $this->initial = $initial;
        $this->touch();
        return $this;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }

    public function setFinal(bool $final): self
    {
        $this->final = $final;
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
