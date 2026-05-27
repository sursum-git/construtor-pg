<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeAiMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeAiMessageRepository::class)]
#[ORM\Table(name: 'runtime_ai_message')]
#[ORM\Index(columns: ['session_id', 'created_at'], name: 'idx_runtime_ai_message_session')]
#[ORM\Index(columns: ['session_id', 'role'], name: 'idx_runtime_ai_message_role')]
class RuntimeAiMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $sessionId = '';

    #[ORM\Column(length: 20)]
    private string $role = 'user';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::JSON)]
    private array $normalizedPayload = [];

    #[ORM\Column(type: Types::JSON)]
    private array $diagnostics = [];

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

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = mb_substr(trim($sessionId), 0, 160);
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $role = strtolower(trim($role));
        $this->role = in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'user';
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getNormalizedPayload(): array
    {
        return $this->normalizedPayload;
    }

    public function setNormalizedPayload(array $normalizedPayload): self
    {
        $this->normalizedPayload = $normalizedPayload;
        return $this;
    }

    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function setDiagnostics(array $diagnostics): self
    {
        $this->diagnostics = $diagnostics;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
