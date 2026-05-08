<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderFieldRepository::class)]
#[ORM\Table(name: 'builder_field')]
#[ORM\Index(name: 'idx_builder_field_entity', columns: ['builder_entity_id'])]
#[ORM\UniqueConstraint(name: 'uniq_builder_field_entity_code', columns: ['builder_entity_id', 'code'])]
class BuilderField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuilderEntity $builderEntity = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $code = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $label = '';

    #[ORM\Column(length: 40)]
    private string $dataType = 'string';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $databaseType = null;

    #[ORM\Column(nullable: true)]
    private ?int $length = null;

    #[ORM\Column(nullable: true)]
    private ?int $precisionValue = null;

    #[ORM\Column(nullable: true)]
    private ?int $scaleValue = null;

    #[ORM\Column]
    private bool $required = false;

    #[ORM\Column]
    private bool $primaryKey = false;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: 'json')]
    private array $options = [];

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
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): self
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getDatabaseType(): ?string
    {
        return $this->databaseType;
    }

    public function setDatabaseType(?string $databaseType): self
    {
        $this->databaseType = $databaseType;
        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setLength(?int $length): self
    {
        $this->length = $length;
        return $this;
    }

    public function getPrecisionValue(): ?int
    {
        return $this->precisionValue;
    }

    public function setPrecisionValue(?int $precisionValue): self
    {
        $this->precisionValue = $precisionValue;
        return $this;
    }

    public function getScaleValue(): ?int
    {
        return $this->scaleValue;
    }

    public function setScaleValue(?int $scaleValue): self
    {
        $this->scaleValue = $scaleValue;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function isPrimaryKey(): bool
    {
        return $this->primaryKey;
    }

    public function setPrimaryKey(bool $primaryKey): self
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}
