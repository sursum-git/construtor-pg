<?php

namespace App\Entity;

use App\Repository\SystemUpdateConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemUpdateConsentRepository::class)]
#[ORM\Table(name: 'system_update_consent')]
#[ORM\Index(name: 'idx_system_update_consent_version', columns: ['release_version', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_system_update_consent_subscriber', columns: ['target_subscriber_code', 'created_at'])]
class SystemUpdateConsent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $releaseVersion = '';

    #[ORM\Column(length: 20)]
    private string $status = 'approved';

    #[ORM\Column(length: 120)]
    private string $approvedBy = 'system';

    #[ORM\Column(length: 30)]
    private string $source = 'ui';

    #[ORM\Column(length: 30)]
    private string $deploymentMode = 'shared';

    #[ORM\Column(length: 120)]
    private string $databaseIdentity = 'db:dev';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $targetSubscriberCode = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $targetSubscriberName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReleaseVersion(): string
    {
        return $this->releaseVersion;
    }

    public function setReleaseVersion(string $releaseVersion): self
    {
        $this->releaseVersion = mb_substr(trim($releaseVersion), 0, 40);
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20) ?: 'approved';
        return $this;
    }

    public function getApprovedBy(): string
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(string $approvedBy): self
    {
        $this->approvedBy = mb_substr(trim($approvedBy), 0, 120) ?: 'system';
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = mb_substr(trim($source), 0, 30) ?: 'ui';
        return $this;
    }

    public function getDeploymentMode(): string
    {
        return $this->deploymentMode;
    }

    public function setDeploymentMode(string $deploymentMode): self
    {
        $this->deploymentMode = mb_substr(trim($deploymentMode), 0, 30) ?: 'shared';
        return $this;
    }

    public function getDatabaseIdentity(): string
    {
        return $this->databaseIdentity;
    }

    public function setDatabaseIdentity(string $databaseIdentity): self
    {
        $this->databaseIdentity = mb_substr(trim($databaseIdentity), 0, 120) ?: 'db:dev';
        return $this;
    }

    public function getTargetSubscriberCode(): ?string
    {
        return $this->targetSubscriberCode;
    }

    public function setTargetSubscriberCode(?string $targetSubscriberCode): self
    {
        $normalized = trim((string) $targetSubscriberCode);
        $this->targetSubscriberCode = $normalized !== '' ? mb_substr($normalized, 0, 120) : null;
        return $this;
    }

    public function getTargetSubscriberName(): ?string
    {
        return $this->targetSubscriberName;
    }

    public function setTargetSubscriberName(?string $targetSubscriberName): self
    {
        $normalized = trim((string) $targetSubscriberName);
        $this->targetSubscriberName = $normalized !== '' ? mb_substr($normalized, 0, 160) : null;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $normalized = trim((string) $reason);
        $this->reason = $normalized !== '' ? $normalized : null;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
