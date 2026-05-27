<?php

namespace App\Runtime;

final class RuntimeEventMessage
{
    public function __construct(private readonly int $eventId)
    {
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }
}
