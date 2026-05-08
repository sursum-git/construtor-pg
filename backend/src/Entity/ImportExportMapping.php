<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ImportExportMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: ImportExportMappingRepository::class)]
#[ORM\Table(name: 'import_export_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_import_export_mapping_code', columns: ['code'])]
class ImportExportMapping
{
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

    #[Assert\Choice(choices: ['import', 'export'])]
    #[ORM\Column(length: 20)]
    private string $direction = 'export';

    #[ORM\Column(length: 40)]
    private string $targetType = 'entity';

    #[ORM\Column(length: 160)]
    private string $targetCode = '';

    #[ORM\Column(length: 40)]
    private string $format = 'json';

    #[ORM\Column(type: 'json')]
    private array $mapping = [];

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

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
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): self
    {
        $this->targetType = $targetType;
        return $this;
    }

    public function getTargetCode(): string
    {
        return $this->targetCode;
    }

    public function setTargetCode(string $targetCode): self
    {
        $this->targetCode = $targetCode;
        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function setMapping(array $mapping): self
    {
        $this->mapping = $mapping;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
