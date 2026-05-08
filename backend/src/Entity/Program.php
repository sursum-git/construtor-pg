<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProgramRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: ProgramRepository::class)]
#[ORM\Table(name: 'builder_program')]
#[ORM\UniqueConstraint(name: 'uniq_builder_program_code', columns: ['code'])]
class Program
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
    private string $title = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $module = null;

    #[ORM\Column(length: 40)]
    private string $programType = 'crud';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $screenId = null;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

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
        $this->code = $code;
        $this->touch();
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();
        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(?string $module): self
    {
        $this->module = $module;
        $this->touch();
        return $this;
    }

    public function getProgramType(): string
    {
        return $this->programType;
    }

    public function setProgramType(string $programType): self
    {
        $this->programType = $programType;
        $this->touch();
        return $this;
    }

    public function getScreenId(): ?string
    {
        return $this->screenId;
    }

    public function setScreenId(?string $screenId): self
    {
        $this->screenId = $screenId;
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
