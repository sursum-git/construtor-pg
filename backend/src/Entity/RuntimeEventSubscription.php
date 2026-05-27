<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeEventSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeEventSubscriptionRepository::class)]
#[ORM\Table(name: 'runtime_event_subscription')]
#[ORM\Index(columns: ['tenant_id', 'event_code', 'enabled', 'priority'], name: 'idx_runtime_event_subscription_match')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_event_subscription_code', columns: ['code'])]
class RuntimeEventSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code = '';

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 160)]
    private string $eventCode = '';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(length: 30)]
    private string $handlerType = 'log';

    #[ORM\Column(type: Types::JSON)]
    private array $condition = [];

    #[ORM\Column(type: Types::JSON)]
    private array $handlerConfig = [];

    #[ORM\Column]
    private int $maxAttempts = 3;

    #[ORM\Column]
    private int $priority = 100;

    #[ORM\Column(length: 240)]
    private string $idempotencyKeyTemplate = '{tenantId}:{subscriptionCode}:{eventId}';

    #[ORM\Column(length: 30)]
    private string $status = 'active';

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

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this->touch(); }
    public function getTenantId(): string { return $this->tenantId; }
    public function setTenantId(string $tenantId): self { $this->tenantId = $tenantId; return $this->touch(); }
    public function getEventCode(): string { return $this->eventCode; }
    public function setEventCode(string $eventCode): self { $this->eventCode = $eventCode; return $this->touch(); }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this->touch(); }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this->touch(); }
    public function getHandlerType(): string { return $this->handlerType; }
    public function setHandlerType(string $handlerType): self { $this->handlerType = $handlerType; return $this->touch(); }
    public function getCondition(): array { return $this->condition; }
    public function setCondition(array $condition): self { $this->condition = $condition; return $this->touch(); }
    public function getHandlerConfig(): array { return $this->handlerConfig; }
    public function setHandlerConfig(array $handlerConfig): self { $this->handlerConfig = $handlerConfig; return $this->touch(); }
    public function getMaxAttempts(): int { return $this->maxAttempts; }
    public function setMaxAttempts(int $maxAttempts): self { $this->maxAttempts = max(1, min(20, $maxAttempts)); return $this->touch(); }
    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $priority): self { $this->priority = $priority; return $this->touch(); }
    public function getIdempotencyKeyTemplate(): string { return $this->idempotencyKeyTemplate; }
    public function setIdempotencyKeyTemplate(string $template): self { $this->idempotencyKeyTemplate = $template !== '' ? $template : '{tenantId}:{subscriptionCode}:{eventId}'; return $this->touch(); }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this->touch(); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    private function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
