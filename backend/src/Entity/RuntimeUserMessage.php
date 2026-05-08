<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeUserMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeUserMessageRepository::class)]
#[ORM\Table(name: 'runtime_user_message')]
#[ORM\Index(columns: ['tenant_id', 'target_user_id', 'status'], name: 'idx_runtime_user_message_user')]
#[ORM\Index(columns: ['tenant_id', 'target_session_id', 'status'], name: 'idx_runtime_user_message_session')]
class RuntimeUserMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120)]
    private string $senderUserId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $senderUserName = null;

    #[ORM\Column(length: 120)]
    private string $targetUserId = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $targetSessionId = null;

    #[ORM\Column(length: 40)]
    private string $type = 'notice';

    #[ORM\Column(length: 30)]
    private string $severity = 'info';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column]
    private bool $actionRequired = false;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

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

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = mb_substr($tenantId, 0, 80);
        return $this;
    }

    public function setSenderUserId(string $senderUserId): self
    {
        $this->senderUserId = mb_substr($senderUserId, 0, 120);
        return $this;
    }

    public function getSenderUserId(): string
    {
        return $this->senderUserId;
    }

    public function setSenderUserName(?string $senderUserName): self
    {
        $this->senderUserName = $senderUserName === null ? null : mb_substr($senderUserName, 0, 160);
        return $this;
    }

    public function getSenderUserName(): ?string
    {
        return $this->senderUserName;
    }

    public function setTargetUserId(string $targetUserId): self
    {
        $this->targetUserId = mb_substr($targetUserId, 0, 120);
        return $this;
    }

    public function getTargetUserId(): string
    {
        return $this->targetUserId;
    }

    public function setTargetSessionId(?string $targetSessionId): self
    {
        $this->targetSessionId = $targetSessionId === null || $targetSessionId === '' ? null : mb_substr($targetSessionId, 0, 160);
        return $this;
    }

    public function getTargetSessionId(): ?string
    {
        return $this->targetSessionId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = mb_substr($type, 0, 40);
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = mb_substr($severity, 0, 30);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = mb_substr($title, 0, 160);
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr($status, 0, 30);
        return $this;
    }

    public function isActionRequired(): bool
    {
        return $this->actionRequired;
    }

    public function setActionRequired(bool $actionRequired): self
    {
        $this->actionRequired = $actionRequired;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function markDelivered(): self
    {
        $this->status = 'delivered';
        $this->deliveredAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function acknowledge(): self
    {
        $this->status = 'acknowledged';
        $this->acknowledgedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
