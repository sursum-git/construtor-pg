<?php

namespace App\Runtime;

class CentralControlGuard
{
    public function __construct(
        private readonly CentralControlResolver $central,
    ) {
    }

    public function ensureCentral(): void
    {
        if ($this->central->isCentralControl()) {
            return;
        }

        throw new RuntimeHttpException(
            'CENTRAL_CONTROL_REQUIRED',
            $this->central->readOnlyReason(),
            403,
            $this->central->resolve()
        );
    }
}
