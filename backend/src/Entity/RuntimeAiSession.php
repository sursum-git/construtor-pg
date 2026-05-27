<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeAiSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeAiSessionRepository::class)]
#[ORM\Table(name: 'runtime_ai_session')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_ai_session_session', columns: ['session_id'])]
#[ORM\Index(columns: ['tenant_id', 'user_id', 'purpose', 'status'], name: 'idx_runtime_ai_session_owner')]
#[ORM\Index(columns: ['tenant_id', 'subscriber_code', 'purpose', 'status'], name: 'idx_runtime_ai_session_subscriber')]
class RuntimeAiSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $userId = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subscriberCode = null;

    #[ORM\Column(length: 80)]
    private string $purpose = 'program_builder';

    #[ORM\Column(length: 120)]
    private string $catalogHash = '';

    #[ORM\Column(length: 40)]
    private string $catalogVersion = '';

    #[ORM\Column(type: Types::JSON)]
    private array $currentDraft = [];

    #[ORM\Column(type: Types::JSON)]
    private array $currentDiagnostics = [];

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastSeenAt = $now;
        $this->expiresAt = $now->modify('+2 hours');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = mb_substr(trim($sessionId), 0, 160);
        return $this;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr(trim($tenantId), 0, 80);
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = mb_substr(trim($userId), 0, 120);
        return $this;
    }

    public function getSubscriberCode(): ?string
    {
        return $this->subscriberCode;
    }

    public function setSubscriberCode(?string $subscriberCode): self
    {
        $normalized = trim((string) $subscriberCode);
        $this->subscriberCode = $normalized !== '' ? mb_substr($normalized, 0, 120) : null;
        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = mb_substr(trim($purpose), 0, 80);
        return $this;
    }

    public function getCatalogHash(): string
    {
        return $this->catalogHash;
    }

    public function setCatalogHash(string $catalogHash): self
    {
        $this->catalogHash = mb_substr(trim($catalogHash), 0, 120);
        return $this;
    }

    public function getCatalogVersion(): string
    {
        return $this->catalogVersion;
    }

    public function setCatalogVersion(string $catalogVersion): self
    {
        $this->catalogVersion = mb_substr(trim($catalogVersion), 0, 40);
        return $this;
    }

    public function getCurrentDraft(): array
    {
        return $this->currentDraft;
    }

    public function setCurrentDraft(array $currentDraft): self
    {
        $this->currentDraft = $currentDraft;
        return $this;
    }

    public function getCurrentDiagnostics(): array
    {
        return $this->currentDiagnostics;
    }

    public function setCurrentDiagnostics(array $currentDiagnostics): self
    {
        $this->currentDiagnostics = $currentDiagnostics;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 30);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(?\DateTimeImmutable $now = null): self
    {
        $this->lastSeenAt = $now ?? new \DateTimeImmutable();
        return $this;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }
}
