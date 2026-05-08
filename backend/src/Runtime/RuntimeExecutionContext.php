<?php

namespace App\Runtime;

use App\Entity\RuntimeTransaction;

class RuntimeExecutionContext
{
    private array $context = [];
    private ?RuntimeTransaction $transaction = null;

    public function open(array $context, ?RuntimeTransaction $transaction = null): void
    {
        $this->context = $context;
        $this->transaction = $transaction;
    }

    public function setTransaction(RuntimeTransaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getTransaction(): ?RuntimeTransaction
    {
        return $this->transaction;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->context[$key] ?? $fallback;
    }

    public function all(): array
    {
        return $this->context;
    }

    public function clear(): void
    {
        $this->context = [];
        $this->transaction = null;
    }
}
