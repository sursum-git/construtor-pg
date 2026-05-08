<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RuntimeEndpointRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: RuntimeEndpointRepository::class)]
#[ORM\Table(name: 'runtime_endpoint')]
#[ORM\UniqueConstraint(name: 'uniq_runtime_endpoint_screen_endpoint', columns: ['screen_id', 'endpoint_id'])]
class RuntimeEndpoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $screenId = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $endpointId = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $handler = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $permission = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: 'json')]
    private array $config = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = $screenId;
        return $this;
    }

    public function getEndpointId(): string
    {
        return $this->endpointId;
    }

    public function setEndpointId(string $endpointId): self
    {
        $this->endpointId = $endpointId;
        return $this;
    }

    public function getHandler(): string
    {
        return $this->handler;
    }

    public function setHandler(string $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function setPermission(?string $permission): self
    {
        $this->permission = $permission;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }
}
