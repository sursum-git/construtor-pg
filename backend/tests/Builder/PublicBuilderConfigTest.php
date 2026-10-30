<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderModule;
use PHPUnit\Framework\TestCase;

final class PublicBuilderConfigTest extends TestCase
{
    public function testPublicConfigExcludesOrmObjectsAndSurvivesJsonRoundTrip(): void
    {
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $config = $reflection->getMethod('publicBuilderConfig')->invoke($service, [
            'programCode' => 'cd0090', '_module' => new BuilderModule(),
            '_entity' => new \stdClass(), 'allowCreate' => true,
        ]);
        self::assertArrayNotHasKey('_module', $config);
        self::assertArrayNotHasKey('_entity', $config);
        self::assertSame($config, json_decode(json_encode($config, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }
}
