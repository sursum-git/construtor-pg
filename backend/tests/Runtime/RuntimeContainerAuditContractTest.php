<?php
namespace App\Tests\Runtime;

use App\Runtime\RuntimeApiEntityActionService;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerAuditContractTest extends TestCase
{
    private function resource(array $definition, array $endpoint, string $operation = 'update'): ?string
    {
        $service = (new \ReflectionClass(RuntimeApiEntityActionService::class))->newInstanceWithoutConstructor();
        return (new \ReflectionMethod($service, 'trustedAuditResource'))->invoke($service, $definition, $endpoint, $operation);
    }

    public function testContainerAuditRequiresTheRegisteredSourceEntityRouteAndStaticOperation(): void
    {
        $definition = ['entityCode' => 'container_painel_progress', 'primaryKey' => 'container_id',
            'apiBinding' => ['sourceCode' => 'motor_progress_container_cabecalho'],
            'apiSource' => ['responseContract' => 'sursum-programs']];
        foreach (['create', 'update', 'delete'] as $operation) {
            $endpoint = ['method' => 'POST', 'url' => 'http://127.0.0.1:19910/med/web/SursumPrograms/containers-cabecalho',
                'bodyTemplate' => ['operation' => $operation, 'resource' => 'containers']];
            self::assertSame('containers', $this->resource($definition, $endpoint, $operation));
            foreach ([['entityCode' => 'another_entity'], ['primaryKey' => 'id'],
                ['apiBinding' => ['sourceCode' => 'another_source']], ['apiSource' => ['responseContract' => 'generic']]] as $change) {
                self::assertNull($this->resource(array_replace($definition, $change), $endpoint, $operation));
            }
            foreach ([['method' => 'GET'], ['url' => 'http://127.0.0.1/another-route'],
                ['bodyTemplate' => ['resource' => 'containers']],
                ['bodyTemplate' => ['operation' => 'read', 'resource' => 'containers']],
                ['bodyTemplate' => ['operation' => $operation, 'resource' => 'containers', 'actor' => 'forged']]] as $change) {
                self::assertNull($this->resource($definition, array_replace($endpoint, $change), $operation));
            }
        }
        self::assertNull($this->resource($definition, ['method' => 'POST', 'url' => '/SursumPrograms/containers-cabecalho',
            'bodyTemplate' => ['operation' => 'read', 'resource' => 'containers']], 'read'));
    }
}
