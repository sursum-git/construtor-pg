<?php

namespace App\Tests\Runtime;

use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class RuntimeEntityDefinitionResolverTest extends TestCase
{
    public function testMissingBuilderEntityReturnsConfigurationError(): void
    {
        $repository = $this->createMock(BuilderEntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['code' => 'produto'])
            ->willReturn(null);

        $resolver = new RuntimeEntityDefinitionResolver($repository, $this->createStub(Connection::class));

        try {
            $resolver->resolve('produto');
            self::fail('Expected runtime configuration error.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('ENTITY_METADATA_NOT_CONFIGURED', $error->getErrorCode());
            self::assertSame(422, $error->getStatusCode());
            self::assertArrayHasKey('minimumRequired', $error->getDetails());
            self::assertSame('persistence', $error->getDetails()['minimumRequired']['builder_entity']['entityType']);
            self::assertSame('entity.crud', $error->getDetails()['minimumRequired']['runtime_endpoint']['handler']);
        }
    }
}
