<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderModuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderModuleRepository::class)]
#[ORM\Table(name: 'builder_module')]
#[ORM\UniqueConstraint(name: 'uniq_builder_module_code', columns: ['code'])]
class BuilderModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 12)]
    private string $abbreviation = '';

    #[ORM\Column]
    private int $numberStart = 1;

    #[ORM\Column]
    private int $numberEnd = 999;

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

    public function getNumberStart(): int
    {
        return $this->numberStart;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): self
    {
        $this->abbreviation = mb_substr(strtolower(trim($abbreviation)), 0, 12);
        $this->touch();
        return $this;
    }

    public function setNumberStart(int $numberStart): self
    {
        $this->numberStart = $numberStart;
        $this->touch();
        return $this;
    }

    public function getNumberEnd(): int
    {
        return $this->numberEnd;
    }

    public function setNumberEnd(int $numberEnd): self
    {
        $this->numberEnd = $numberEnd;
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
