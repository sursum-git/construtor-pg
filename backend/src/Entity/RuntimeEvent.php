<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeEventRepository::class)]
#[ORM\Table(name: 'runtime_event')]
#[ORM\Index(columns: ['tenant_id', 'status', 'created_at'], name: 'idx_runtime_event_status')]
#[ORM\Index(columns: ['event_code', 'status'], name: 'idx_runtime_event_code')]
#[ORM\Index(columns: ['entity_code', 'record_id'], name: 'idx_runtime_event_record')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_event_event_id', columns: ['event_id'])]
class RuntimeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $eventId = '';

    #[ORM\Column(length: 160)]
    private string $eventCode = '';

    #[ORM\Column(length: 80)]
    private string $source = 'runtime';

    #[ORM\Column(length: 80)]
    private string $tenantId = 'default';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $screenId = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $programCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $entityCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $recordId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $operation = null;

    #[ORM\Column(length: 30)]
    private string $status = 'published';

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\ManyToOne(targetEntity: RuntimeTransaction::class)]
    #[ORM\JoinColumn(name: 'transaction_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RuntimeTransaction $transaction = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->occurredAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getEventId(): string { return $this->eventId; }
    public function setEventId(string $eventId): self { $this->eventId = $eventId; return $this; }
    public function getEventCode(): string { return $this->eventCode; }
    public function setEventCode(string $eventCode): self { $this->eventCode = $eventCode; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }
    public function getTenantId(): string { return $this->tenantId; }
    public function setTenantId(string $tenantId): self { $this->tenantId = $tenantId; return $this; }
    public function getUserId(): ?string { return $this->userId; }
    public function setUserId(?string $userId): self { $this->userId = $userId; return $this; }
    public function getSessionId(): ?string { return $this->sessionId; }
    public function setSessionId(?string $sessionId): self { $this->sessionId = $sessionId; return $this; }
    public function getScreenId(): ?string { return $this->screenId; }
    public function setScreenId(?string $screenId): self { $this->screenId = $screenId; return $this; }
    public function getProgramCode(): ?string { return $this->programCode; }
    public function setProgramCode(?string $programCode): self { $this->programCode = $programCode; return $this; }
    public function getEntityCode(): ?string { return $this->entityCode; }
    public function setEntityCode(?string $entityCode): self { $this->entityCode = $entityCode; return $this; }
    public function getRecordId(): ?string { return $this->recordId; }
    public function setRecordId(null|string|int $recordId): self { $this->recordId = $recordId === null ? null : (string) $recordId; return $this; }
    public function getOperation(): ?string { return $this->operation; }
    public function setOperation(?string $operation): self { $this->operation = $operation; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; $this->touch(); return $this; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $payload): self { $this->payload = $payload; return $this; }
    public function getMetadata(): array { return $this->metadata; }
    public function setMetadata(array $metadata): self { $this->metadata = $metadata; return $this; }
    public function getTransaction(): ?RuntimeTransaction { return $this->transaction; }
    public function setTransaction(?RuntimeTransaction $transaction): self { $this->transaction = $transaction; return $this; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function setOccurredAt(\DateTimeImmutable $occurredAt): self { $this->occurredAt = $occurredAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function markProcessed(): self { $this->status = 'processed'; $this->processedAt = new \DateTimeImmutable(); return $this->touch(); }
    public function markFailed(): self { $this->status = 'failed'; return $this->touch(); }
    private function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
