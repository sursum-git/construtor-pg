<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SystemParameterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: SystemParameterRepository::class)]
#[ORM\Table(name: 'system_parameter')]
#[ORM\UniqueConstraint(name: 'uniq_system_parameter_code', columns: ['code'])]
#[ORM\Index(columns: ['data_type', 'enabled'], name: 'idx_system_parameter_type')]
#[ORM\Index(columns: ['option_list_id'], name: 'idx_system_parameter_option_list')]
class SystemParameter
{
    public const DATA_TYPES = [
        'string',
        'text',
        'integer',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'json',
        'option',
        'multi_option',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $code = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 40)]
    private string $dataType = 'string';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'option_list_id', nullable: true, onDelete: 'SET NULL')]
    private ?SystemOptionList $optionList = null;

    #[ORM\Column]
    private bool $required = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $defaultValue = null;

    #[ORM\Column]
    private bool $enabled = true;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = mb_substr(trim($code), 0, 120);
        $this->touch();
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr(trim($name), 0, 160);
        $this->touch();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $description = $description === null ? null : trim($description);
        $this->description = $description === '' ? null : $description;
        $this->touch();
        return $this;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): self
    {
        $this->dataType = in_array($dataType, self::DATA_TYPES, true) ? $dataType : 'string';
        $this->touch();
        return $this;
    }

    public function getOptionList(): ?SystemOptionList
    {
        return $this->optionList;
    }

    public function setOptionList(?SystemOptionList $optionList): self
    {
        $this->optionList = $optionList;
        $this->touch();
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        $this->touch();
        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(mixed $defaultValue): self
    {
        $this->defaultValue = $defaultValue;
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
