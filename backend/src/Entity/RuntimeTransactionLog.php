<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeTransactionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeTransactionLogRepository::class)]
#[ORM\Table(name: 'runtime_transaction_log')]
#[ORM\Index(columns: ['transaction_id'], name: 'IDX_RUNTIME_TRANSACTION_LOG_TRANSACTION')]
#[ORM\Index(columns: ['event_type', 'created_at'], name: 'idx_runtime_transaction_log_event')]
class RuntimeTransactionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RuntimeTransaction::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RuntimeTransaction $transaction;

    #[ORM\Column(length: 80)]
    private string $eventType = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON)]
    private array $beforeData = [];

    #[ORM\Column(type: Types::JSON)]
    private array $afterData = [];

    #[ORM\Column(type: Types::JSON)]
    private array $diffData = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

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

    public function getTransaction(): RuntimeTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(RuntimeTransaction $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = mb_substr($eventType, 0, 80);
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getBeforeData(): array
    {
        return $this->beforeData;
    }

    public function setBeforeData(array $beforeData): self
    {
        $this->beforeData = $beforeData;
        return $this;
    }

    public function getAfterData(): array
    {
        return $this->afterData;
    }

    public function setAfterData(array $afterData): self
    {
        $this->afterData = $afterData;
        return $this;
    }

    public function getDiffData(): array
    {
        return $this->diffData;
    }

    public function setDiffData(array $diffData): self
    {
        $this->diffData = $diffData;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
