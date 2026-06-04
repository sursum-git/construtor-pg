<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ScreenDefinitionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: ScreenDefinitionRepository::class)]
#[ORM\Table(name: 'screen_definition')]
#[ORM\UniqueConstraint(name: 'uniq_screen_definition_screen_id', columns: ['screen_id'])]
class ScreenDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $screenId = '';

    #[Assert\Choice(choices: ['crud', 'home', 'process', 'custom', 'analytics', 'report', 'special_document', 'regulated_document', 'master_detail'])]
    #[ORM\Column(length: 30)]
    private string $pageType = 'crud';

    #[ORM\Column(length: 20)]
    private string $schemaVersion = '1.0';

    #[ORM\Column(type: 'json')]
    private array $definition = [];

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column(length: 40)]
    private string $version = '1.0.0';

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

    public function getScreenId(): string
    {
        return $this->screenId;
    }

    public function setScreenId(string $screenId): self
    {
        $this->screenId = $screenId;
        $this->touch();
        return $this;
    }

    public function getPageType(): string
    {
        return $this->pageType;
    }

    public function setPageType(string $pageType): self
    {
        $this->pageType = $pageType;
        $this->touch();
        return $this;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(string $schemaVersion): self
    {
        $this->schemaVersion = $schemaVersion;
        $this->touch();
        return $this;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function setDefinition(array $definition): self
    {
        $this->definition = $definition;
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
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

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
