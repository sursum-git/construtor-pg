<?php

namespace App\Odoo;

final class OdooExecutionContext
{
    public function __construct(
        private readonly array $config,
        private readonly int $uid,
    ) {
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getUid(): int
    {
        return $this->uid;
    }
}
