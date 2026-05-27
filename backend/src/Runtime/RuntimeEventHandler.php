<?php

namespace App\Runtime;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RuntimeEventHandler
{
    public function __construct(private readonly RuntimeEventService $events)
    {
    }

    public function __invoke(RuntimeEventMessage $message): void
    {
        $this->events->process($message->getEventId());
    }
}
