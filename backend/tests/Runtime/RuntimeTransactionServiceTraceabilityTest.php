<?php

namespace App\Tests\Runtime;

use App\Entity\RuntimeTransaction;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeExecutionContext;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RuntimeTransactionServiceTraceabilityTest extends TestCase
{
    public function testBuildTraceabilityUsesEndpointMetadataAndEnvironment(): void
    {
        $definitions = $this->createMock(RuntimeEntityDefinitionResolver::class);
        $definitions->expects(self::once())
            ->method('resolve')
            ->with('cliente')
            ->willReturn([
                'entityCode' => 'cliente',
                'tableName' => 'cliente',
                'primaryKey' => 'id',
                'fields' => [
                    'id' => ['column' => 'id', 'dataType' => 'integer', 'databaseType' => 'integer', 'writable' => false, 'readable' => true],
                    'nome' => ['column' => 'nome', 'dataType' => 'string', 'databaseType' => 'varchar', 'writable' => true, 'readable' => true],
                ],
            ]);

        $env = $this->createMock(RuntimeEnvironmentIdentityResolver::class);
        $env->expects(self::once())
            ->method('resolve')
            ->willReturn([
                'databaseIdentity' => 'db:prod-principal',
                'databaseEnvironment' => 'prod',
            ]);

        $service = new RuntimeTransactionService(
            $this->createStub(EntityManagerInterface::class),
            $this->permissionResolver(),
            new RuntimeExecutionContext(),
            $definitions,
            $env,
        );

        $reflection = new \ReflectionMethod($service, 'buildTraceability');
        $reflection->setAccessible(true);
        $traceability = $reflection->invoke(
            $service,
            'cadastros.clientes',
            'read',
            'entity.crud',
            [
                'programId' => 'cd0001',
                '_runtimeEndpoint' => [
                    'traceability' => [
                        'programCode' => 'cd0001',
                        'programVersion' => '1.2.3',
                        'builderProgramVersionId' => 77,
                        'builderEntityVersionId' => 88,
                        'screenDefinitionVersion' => '1.2.3',
                        'customizationKind' => 'standard',
                    ],
                ],
            ],
            'cliente',
        );

        self::assertSame('cd0001', $traceability['programCode']);
        self::assertSame('1.2.3', $traceability['programVersion']);
        self::assertSame(77, $traceability['builderProgramVersionId']);
        self::assertSame(88, $traceability['builderEntityVersionId']);
        self::assertSame('prod', $traceability['databaseEnvironment']);
        self::assertSame('db:prod-principal', $traceability['databaseIdentity']);
        self::assertArrayHasKey('schemaFingerprint', $traceability);
        self::assertSame('tenant-a', $traceability['subscriberId']);
    }

    public function testLogMetadataReceivesImpersonationContext(): void
    {
        $service = new RuntimeTransactionService(
            $this->createStub(EntityManagerInterface::class),
            $this->permissionResolver(),
            new RuntimeExecutionContext(),
            $this->createStub(RuntimeEntityDefinitionResolver::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
        );
        $transaction = (new RuntimeTransaction())->setRequestContext([
            'traceability' => ['screenId' => 'cadastros.clientes'],
            'impersonation' => [
                'enabled' => true,
                'actorUserId' => 'admin',
                'targetUserId' => 'joao',
                'reason' => 'Suporte.',
            ],
        ]);

        $property = new \ReflectionProperty($service, 'current');
        $property->setAccessible(true);
        $property->setValue($service, $transaction);

        $method = new \ReflectionMethod($service, 'mergeTraceabilityMetadata');
        $method->setAccessible(true);
        $metadata = $method->invoke($service, []);

        self::assertSame('cadastros.clientes', $metadata['screenId']);
        self::assertSame('admin', $metadata['originalUserId']);
        self::assertSame('joao', $metadata['effectiveUserId']);
        self::assertSame('Suporte.', $metadata['impersonationReason']);
    }

    private function permissionResolver(): PermissionResolver
    {
        $resolver = $this->createStub(PermissionResolver::class);
        $resolver->method('getTenantId')->willReturn('tenant-a');
        $resolver->method('getUserId')->willReturn('joao');
        return $resolver;
    }
}
