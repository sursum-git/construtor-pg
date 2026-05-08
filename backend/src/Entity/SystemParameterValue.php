<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SystemParameterValueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: SystemParameterValueRepository::class)]
#[ORM\Table(name: 'system_parameter_value')]
#[ORM\Index(columns: ['parameter_id', 'establishment_code', 'enabled', 'starts_at'], name: 'idx_system_parameter_value_scope')]
#[ORM\Index(columns: ['establishment_code'], name: 'idx_system_parameter_value_establishment')]
class SystemParameterValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parameter_id', nullable: false, onDelete: 'CASCADE')]
    private ?SystemParameter $parameter = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $establishmentCode = null;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $value = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->startsAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParameter(): ?SystemParameter
    {
        return $this->parameter;
    }

    public function setParameter(?SystemParameter $parameter): self
    {
        $this->parameter = $parameter;
        $this->touch();
        return $this;
    }

    public function getEstablishmentCode(): ?string
    {
        return $this->establishmentCode;
    }

    public function setEstablishmentCode(?string $establishmentCode): self
    {
        $establishmentCode = $establishmentCode === null ? null : trim($establishmentCode);
        $this->establishmentCode = $establishmentCode === '' ? null : mb_substr((string) $establishmentCode, 0, 120);
        $this->touch();
        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;
        $this->touch();
        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;
        $this->touch();
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): self
    {
        $this->value = $value;
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
