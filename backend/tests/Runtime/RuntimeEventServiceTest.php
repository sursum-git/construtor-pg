<?php

namespace App\Tests\Runtime;

use App\Entity\RuntimeEvent;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;

class RuntimeEventServiceTest extends TestCase
{
    public function testConditionEqMatchesTopLevelPayload(): void
    {
        $service = $this->serviceWithoutConstructor();
        $event = (new RuntimeEvent())
            ->setEventId('evt_test')
            ->setEventCode('runtime.entity.created')
            ->setTenantId('tenant-a')
            ->setPayload(['entityCode' => 'produto', 'after' => ['status' => 'ATIVO']]);

        $result = $this->invokePrivate($service, 'matchesCondition', [$event, [
            'field' => 'entityCode',
            'operator' => 'eq',
            'value' => 'produto',
        ]]);

        self::assertTrue($result);
    }

    public function testConditionFalseDoesNotMatch(): void
    {
        $service = $this->serviceWithoutConstructor();
        $event = (new RuntimeEvent())
            ->setEventId('evt_test')
            ->setEventCode('runtime.entity.created')
            ->setTenantId('tenant-a')
            ->setPayload(['entityCode' => 'cliente']);

        $result = $this->invokePrivate($service, 'matchesCondition', [$event, [
            'field' => 'entityCode',
            'operator' => 'eq',
            'value' => 'produto',
        ]]);

        self::assertFalse($result);
    }

    public function testExecutableHandlerConfigIsRejected(): void
    {
        $service = $this->serviceWithoutConstructor();

        $this->expectException(RuntimeHttpException::class);
        $this->invokePrivate($service, 'rejectExecutableConfig', [[
            'script' => 'function () { return true; }',
        ]]);
    }

    private function serviceWithoutConstructor(): RuntimeEventService
    {
        return (new \ReflectionClass(RuntimeEventService::class))->newInstanceWithoutConstructor();
    }

    private function invokePrivate(object $target, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
