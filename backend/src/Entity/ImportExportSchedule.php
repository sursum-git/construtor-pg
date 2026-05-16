<?php

namespace App\Entity;

use App\Repository\ImportExportScheduleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportExportScheduleRepository::class)]
#[ORM\Table(name: 'import_export_schedule')]
#[ORM\UniqueConstraint(name: 'uniq_import_export_schedule_code', columns: ['code'])]
class ImportExportSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $code = '';

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 120)]
    private string $mappingCode = '';

    #[ORM\Column(length: 20)]
    private string $frequency = 'daily';

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: 'json')]
    private array $parameters = [];

    #[ORM\Column(nullable: true)]
    private ?int $intervalMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyHour = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyMinute = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextRunAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastStatus = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $updatedBy = null;

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
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getMappingCode(): string { return $this->mappingCode; }
    public function setMappingCode(string $mappingCode): self { $this->mappingCode = $mappingCode; return $this; }
    public function getFrequency(): string { return $this->frequency; }
    public function setFrequency(string $frequency): self { $this->frequency = $frequency; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function getParameters(): array { return $this->parameters; }
    public function setParameters(array $parameters): self { $this->parameters = $parameters; return $this; }
    public function getIntervalMinutes(): ?int { return $this->intervalMinutes; }
    public function setIntervalMinutes(?int $intervalMinutes): self { $this->intervalMinutes = $intervalMinutes; return $this; }
    public function getDailyHour(): ?int { return $this->dailyHour; }
    public function setDailyHour(?int $dailyHour): self { $this->dailyHour = $dailyHour; return $this; }
    public function getDailyMinute(): ?int { return $this->dailyMinute; }
    public function setDailyMinute(?int $dailyMinute): self { $this->dailyMinute = $dailyMinute; return $this; }
    public function getNextRunAt(): ?\DateTimeImmutable { return $this->nextRunAt; }
    public function setNextRunAt(?\DateTimeImmutable $nextRunAt): self { $this->nextRunAt = $nextRunAt; return $this; }
    public function getLastRunAt(): ?\DateTimeImmutable { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self { $this->lastRunAt = $lastRunAt; return $this; }
    public function getLastStatus(): ?string { return $this->lastStatus; }
    public function setLastStatus(?string $lastStatus): self { $this->lastStatus = $lastStatus; return $this; }
    public function getUpdatedBy(): ?string { return $this->updatedBy; }
    public function setUpdatedBy(?string $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
