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

    public function testRejectsProgramUpdatesWithoutPublishDefaultsStep(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('programUpdates sem publish_runtime_defaults');

        $validator->assertValid([
            [
                'version' => '2.3.5',
                'requiresAppliedUpdates' => [],
                'replaces' => [],
                'steps' => [
                    ['code' => 'integrity_monitor'],
                ],
                'programUpdates' => [
                    [
                        'programCode' => 'admin.integracoes',
                        'targetPublishedVersion' => '1.1.0',
                    ],
                ],
            ],
        ]);
    }

    public function testAcceptsReleaseUsingDefaultApplicationPolicyByCategory(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $validator->assertValid([
            [
                'version' => '2.5.3',
                'category' => 'security_critical',
                'requiresAppliedUpdates' => [],
                'replaces' => [],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testRejectsPolicyOverrideWithoutJustification(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('applicationPolicyOverride sem justificativa');

        $validator->assertValid([
            [
                'version' => '2.6.1',
                'category' => 'required_structural',
                'autoApplySaas' => false,
                'autoApplyOnPrem' => false,
                'requiresSubscriberConsent' => true,
                'blocksNextUpdates' => true,
                'internetRequired' => false,
                'requiresAppliedUpdates' => [],
                'replaces' => [],
                'metadata' => [
                    'requiresBackup' => true,
                    'requiresMaintenanceMode' => true,
                    'applicationPolicyOverride' => true,
                ],
            ],
        ]);
    }

    public function testRejectsPolicyOverrideWithoutOverrideFlag(): void
    {
        $validator = new SystemUpdateManifestRulesValidator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('diverge da politica padrao da categoria');

        $validator->assertValid([
            [
                'version' => '2.6.1',
                'category' => 'required_structural',
                'autoApplySaas' => false,
                'autoApplyOnPrem' => false,
                'requiresSubscriberConsent' => true,
                'blocksNextUpdates' => true,
                'internetRequired' => false,
                'requiresAppliedUpdates' => [],
                'replaces' => [],
                'metadata' => [
                    'requiresBackup' => true,
                    'requiresMaintenanceMode' => true,
                ],
            ],
        ]);
    }
}
