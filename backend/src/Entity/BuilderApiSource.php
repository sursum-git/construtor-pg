<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BuilderApiSourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity(repositoryClass: BuilderApiSourceRepository::class)]
#[ORM\Table(name: 'builder_api_source')]
#[ORM\UniqueConstraint(name: 'uniq_builder_api_source_code', columns: ['code'])]
class BuilderApiSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code = '';

    #[ORM\Column(length: 180)]
    private string $name = '';

    #[ORM\Column(length: 32)]
    private string $authMode = 'none';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $baseUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $openapiUrl = null;

    #[ORM\Column(length: 32)]
    private string $status = 'active';

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

    public function getAuthMode(): string
    {
        return $this->authMode;
    }

    public function setAuthMode(string $authMode): self
    {
        $this->authMode = mb_substr(trim($authMode), 0, 32);
        $this->touch();
        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): self
    {
        $this->baseUrl = $baseUrl !== null ? mb_substr(trim($baseUrl), 0, 255) : null;
        $this->touch();
        return $this;
    }

    public function getOpenapiUrl(): ?string
    {
        return $this->openapiUrl;
    }

    public function setOpenapiUrl(?string $openapiUrl): self
    {
        $this->openapiUrl = $openapiUrl !== null ? mb_substr(trim($openapiUrl), 0, 255) : null;
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 32);
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
