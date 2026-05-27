<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeEventDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeEventDeliveryRepository::class)]
#[ORM\Table(name: 'runtime_event_delivery')]
#[ORM\Index(columns: ['tenant_id', 'status', 'created_at'], name: 'idx_runtime_event_delivery_status')]
#[ORM\Index(columns: ['event_id'], name: 'idx_runtime_event_delivery_event')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_event_delivery_idempotency', columns: ['idempotency_key'])]
class RuntimeEventDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RuntimeEvent::class)]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private RuntimeEvent $event;

    #[ORM\ManyToOne(targetEntity: RuntimeEventSubscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private RuntimeEventSubscription $subscription;

    #[ORM\ManyToOne(targetEntity: RuntimeTransaction::class)]
    #[ORM\JoinColumn(name: 'transaction_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RuntimeTransaction $transaction = null;

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(length: 240)]
    private string $idempotencyKey = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::JSON)]
    private array $result = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(RuntimeEvent $event, RuntimeEventSubscription $subscription)
    {
        $this->event = $event;
        $this->subscription = $subscription;
        $this->tenantId = $event->getTenantId();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): RuntimeEvent { return $this->event; }
    public function getSubscription(): RuntimeEventSubscription { return $this->subscription; }
    public function getTransaction(): ?RuntimeTransaction { return $this->transaction; }
    public function setTransaction(?RuntimeTransaction $transaction): self { $this->transaction = $transaction; return $this; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getStatus(): string { return $this->status; }
    public function getAttempts(): int { return $this->attempts; }
    public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    public function setIdempotencyKey(string $idempotencyKey): self { $this->idempotencyKey = $idempotencyKey; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function getResult(): array { return $this->result; }
    public function markRunning(): self { $this->status = 'running'; ++$this->attempts; $this->startedAt = new \DateTimeImmutable(); return $this->touch(); }
    public function markSucceeded(array $result = []): self { $this->status = 'succeeded'; $this->lastError = null; $this->result = $result; $this->finishedAt = new \DateTimeImmutable(); return $this->touch(); }
    public function markSkipped(array $result = []): self { $this->status = 'skipped'; $this->lastError = null; $this->result = $result; $this->finishedAt = new \DateTimeImmutable(); return $this->touch(); }
    public function markFailed(string $error, array $result = []): self { $this->status = 'failed'; $this->lastError = mb_substr($error, 0, 4000); $this->result = $result; $this->finishedAt = new \DateTimeImmutable(); return $this->touch(); }
    private function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
