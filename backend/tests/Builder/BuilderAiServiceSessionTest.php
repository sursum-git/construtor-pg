<?php

namespace App\Tests\Builder;

use App\Builder\BuilderAiService;
use App\Builder\BuilderAiSessionService;
use App\Builder\BuilderAiSettingsService;
use App\Builder\ExternalBuilderContextService;
use App\Builder\ProgramBuilderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BuilderAiServiceSessionTest extends TestCase
{
    public function testAiRuleSafetyBlocksExecutableRuleReferences(): void
    {
        $service = new BuilderAiService(
            $this->createStub(BuilderAiSettingsService::class),
            $this->createStub(ExternalBuilderContextService::class),
            $this->createStub(BuilderAiSessionService::class),
            $this->createStub(ProgramBuilderService::class),
            $this->createStub(LoggerInterface::class),
        );

        $result = $this->invokePrivate($service, 'enforceAiDeclarativeRules', [[
            'entityDraft' => [
                'rules' => [[
                    'id' => 'regra-tecnica',
                    'label' => 'Regra tecnica',
                    'type' => 'class_method',
                    'className' => 'App\\Runtime\\BusinessRule\\ConfiguredEntityRuleMethods',
                    'methodName' => 'validate',
                ]],
            ],
            'programDraft' => [],
        ]]);

        self::assertTrue($result['blocked']);
        self::assertSame([], $result['draft']['entityDraft']['rules']);
        self::assertSame('ai_rule_safety', $result['diagnostics'][0]['source']);
    }

    public function testAiRuleSafetyAllowsRequiredWhenRules(): void
    {
        $service = new BuilderAiService(
            $this->createStub(BuilderAiSettingsService::class),
            $this->createStub(ExternalBuilderContextService::class),
            $this->createStub(BuilderAiSessionService::class),
            $this->createStub(ProgramBuilderService::class),
            $this->createStub(LoggerInterface::class),
        );

        $result = $this->invokePrivate($service, 'enforceAiDeclarativeRules', [[
            'entityDraft' => [
                'rules' => [[
                    'id' => 'descr-obrigatoria',
                    'label' => 'Descricao obrigatoria',
                    'type' => 'requiredWhen',
                    'field' => 'c_descr',
                    'when' => ['field' => 'log_ativo', 'equals' => true],
                    'message' => 'Informe a descricao.',
                ]],
            ],
            'programDraft' => [],
        ]]);

        self::assertFalse($result['blocked']);
        self::assertSame('requiredWhen', $result['draft']['entityDraft']['rules'][0]['type']);
        self::assertSame([], $result['diagnostics']);
    }

    private function invokePrivate(object $target, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
