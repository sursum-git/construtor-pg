<?php

namespace App\Tests\Runtime;

use App\Runtime\SystemUpdateManifestRulesValidator;
use PHPUnit\Framework\TestCase;

class SystemUpdateManifestRulesValidatorTest extends TestCase
{
    public function testRejectsManifestWithMissingDependency(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('depende de version inexistente');

        $validator->assertValid([
            [
                'version' => '2.4.0',
                'requiresAppliedUpdates' => ['2.3.5'],
                'replaces' => [],
            ],
        ]);
    }

    public function testRejectsManifestWithDependencyCycle(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ciclo de dependencia detectado');

        $validator->assertValid([
            [
                'version' => '2.3.5',
                'requiresAppliedUpdates' => ['2.4.0'],
                'replaces' => [],
            ],
            [
                'version' => '2.4.0',
                'requiresAppliedUpdates' => ['2.3.5'],
                'replaces' => [],
            ],
        ]);
    }

    public function testRejectsManifestWithUnknownStep(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('step nao suportado');

        $validator->assertValid([
            [
                'version' => '2.3.5',
                'requiresAppliedUpdates' => [],
                'replaces' => [],
                'steps' => [
                    ['code' => 'step_inexistente'],
                ],
            ],
        ]);
    }
}
