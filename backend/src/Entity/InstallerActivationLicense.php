<?php

namespace App\Entity;

use App\Repository\InstallerActivationLicenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallerActivationLicenseRepository::class)]
#[ORM\Table(name: 'installer_activation_license')]
#[ORM\UniqueConstraint(name: 'uniq_installer_activation_license_subscriber', columns: ['subscriber_code'])]
#[ORM\Index(name: 'idx_installer_activation_license_status', columns: ['status', 'expires_at'])]
class InstallerActivationLicense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $subscriberCode = '';

    #[ORM\Column(length: 180)]
    private string $subscriberName = '';

    #[ORM\Column(length: 180)]
    private string $activationEmail = '';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: Types::JSON)]
    private array $allowedProfiles = ['subscriber'];

    #[ORM\Column(type: Types::JSON)]
    private array $allowedModes = ['docker', 'native', 'saas-docker'];

    #[ORM\Column]
    private int $maxActivations = 1;

    #[ORM\Column]
    private int $activationCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastActivatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getSubscriberCode(): string
    {
        return $this->subscriberCode;
    }

    public function setSubscriberCode(string $subscriberCode): self
    {
        $this->subscriberCode = mb_substr(trim($subscriberCode), 0, 120);
        $this->touch();
        return $this;
    }

    public function getSubscriberName(): string
    {
        return $this->subscriberName;
    }

    public function setSubscriberName(string $subscriberName): self
    {
        $this->subscriberName = mb_substr(trim($subscriberName), 0, 180);
        $this->touch();
        return $this;
    }

    public function getActivationEmail(): string
    {
        return $this->activationEmail;
    }

    public function setActivationEmail(string $activationEmail): self
    {
        $this->activationEmail = mb_substr(trim($activationEmail), 0, 180);
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20) ?: 'active';
        $this->touch();
        return $this;
    }

    public function getAllowedProfiles(): array
    {
        return $this->allowedProfiles;
    }

    public function setAllowedProfiles(array $allowedProfiles): self
    {
        $this->allowedProfiles = array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $allowedProfiles)));
        $this->touch();
        return $this;
    }

    public function getAllowedModes(): array
    {
        return $this->allowedModes;
    }

    public function setAllowedModes(array $allowedModes): self
    {
        $this->allowedModes = array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $allowedModes)));
        $this->touch();
        return $this;
    }

    public function getMaxActivations(): int
    {
        return $this->maxActivations;
    }

    public function setMaxActivations(int $maxActivations): self
    {
        $this->maxActivations = max(0, $maxActivations);
        $this->touch();
        return $this;
    }

    public function getActivationCount(): int
    {
        return $this->activationCount;
    }

    public function setActivationCount(int $activationCount): self
    {
        $this->activationCount = max(0, $activationCount);
        $this->touch();
        return $this;
    }

    public function incrementActivationCount(): self
    {
        $this->activationCount++;
        $this->lastActivatedAt = new \DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        $this->touch();
        return $this;
    }

    public function getLastActivatedAt(): ?\DateTimeImmutable
    {
        return $this->lastActivatedAt;
    }

    public function setLastActivatedAt(?\DateTimeImmutable $lastActivatedAt): self
    {
        $this->lastActivatedAt = $lastActivatedAt;
        $this->touch();
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $normalized = trim((string) $notes);
        $this->notes = $normalized !== '' ? $normalized : null;
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
