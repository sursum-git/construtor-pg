<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SystemLiteralTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: SystemLiteralTranslationRepository::class)]
#[ORM\Table(name: 'system_literal_translation')]
#[ORM\UniqueConstraint(name: 'uniq_system_literal_translation_code_locale', columns: ['code', 'locale'])]
#[ORM\Index(columns: ['locale', 'enabled'], name: 'idx_system_literal_translation_locale')]
#[ORM\Index(columns: ['context'], name: 'idx_system_literal_translation_context')]
class SystemLiteralTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 160)]
    private string $code = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 20)]
    private string $locale = 'pt-BR';

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $text = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $enabled = true;

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
        $this->code = mb_substr(trim($code), 0, 160);
        $this->touch();

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = mb_substr(trim($locale), 0, 20) ?: 'pt-BR';
        $this->touch();

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = trim($text);
        $this->touch();

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $context = $context === null ? null : trim($context);
        $this->context = $context === '' ? null : mb_substr($context, 0, 120);
        $this->touch();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $description = $description === null ? null : trim($description);
        $this->description = $description === '' ? null : $description;
        $this->touch();

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
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
