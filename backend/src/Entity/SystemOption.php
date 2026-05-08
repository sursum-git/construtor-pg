<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SystemOptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: SystemOptionRepository::class)]
#[ORM\Table(name: 'system_option')]
#[ORM\UniqueConstraint(name: 'uniq_system_option_code_per_list', columns: ['option_list_id', 'code'])]
#[ORM\Index(columns: ['option_list_id', 'enabled'], name: 'idx_system_option_lookup')]
class SystemOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'option_list_id', nullable: false, onDelete: 'CASCADE')]
    private ?SystemOptionList $optionList = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $code = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $description = '';

    #[ORM\Column]
    private int $position = 0;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = mb_substr(trim($description), 0, 255);
        $this->touch();
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(0, $position);
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
