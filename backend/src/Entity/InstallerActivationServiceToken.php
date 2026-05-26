<?php

namespace App\Entity;

use App\Repository\InstallerActivationServiceTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallerActivationServiceTokenRepository::class)]
#[ORM\Table(name: 'installer_activation_service_token')]
#[ORM\UniqueConstraint(name: 'uniq_installer_activation_service_token_code', columns: ['code'])]
#[ORM\Index(name: 'idx_installer_activation_service_token_status', columns: ['status', 'expires_at'])]
class InstallerActivationServiceToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code = '';

    #[ORM\Column(length: 180)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 255)]
    private string $tokenHash = '';

    #[ORM\Column(type: Types::JSON)]
    private array $allowedProfiles = ['subscriber'];

    #[ORM\Column(type: Types::JSON)]
    private array $allowedModes = ['saas-docker'];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private int $usageCount = 0;

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
        $this->name = mb_substr(trim($name), 0, 180);
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

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = mb_substr(trim($tokenHash), 0, 255);
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

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function registerUse(array $entry): self
    {
        $this->usageCount++;
        $this->lastUsedAt = new \DateTimeImmutable();
        $history = is_array($this->metadata['usageHistory'] ?? null) ? $this->metadata['usageHistory'] : [];
        $history[] = $entry + ['usedAt' => $this->lastUsedAt->format(DATE_ATOM)];
        $this->metadata['usageHistory'] = array_slice($history, -30);
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
