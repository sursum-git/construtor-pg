<?php

namespace App\Tests\Runtime;

use App\Runtime\HomeSupportService;
use App\Runtime\HomeRuntimeHandler;
use App\Runtime\RuntimeNotificationService;
use PHPUnit\Framework\TestCase;

class HomeRuntimeHandlerTest extends TestCase
{
    public function testAiSendUsesProgramContext(): void
    {
        $response = $this->createHandler()->handle('aiSend', [
            'message' => ['text' => 'qual o estado da tela?'],
            'context' => $this->contextPayload(),
        ]);

        self::assertSame('clientes-crud', $response['context']['programId']);
        self::assertSame('clientes-crud', $response['context']['programCode']);
        self::assertSame('Clientes', $response['context']['programTitle']);
        self::assertSame('cadastros.clientes', $response['context']['programScreenId']);
        self::assertSame('operacional', $response['context']['moduleId']);
        self::assertSame('Clientes', $response['context']['currentProgram']['title']);
        self::assertStringContainsString('Clientes', $response['messages'][0]['text']);
    }

    public function testSupportRequestReturnsProgramContext(): void
    {
        $support = $this->createMock(HomeSupportService::class);
        $support->expects(self::once())
            ->method('createSupportRequest')
            ->with(
                ['id' => 'suporte'],
                'normal',
                'Erro na tela',
                'Falha ao salvar',
                self::callback(function (array $context): bool {
                    return ($context['programId'] ?? null) === 'clientes-crud'
                        && ($context['programCode'] ?? null) === 'clientes-crud'
                        && ($context['programTitle'] ?? null) === 'Clientes';
                })
            )
            ->willReturn([
                'ok' => true,
                'protocol' => 'ATD-20260518000000',
            ]);

        $response = $this->createHandler($support)->handle('supportCreateRequest', [
            'sector' => ['id' => 'suporte'],
            'subject' => 'Erro na tela',
            'description' => 'Falha ao salvar',
            'context' => $this->contextPayload(),
        ]);

        self::assertTrue($response['ok']);
        self::assertSame('clientes-crud', $response['programId']);
        self::assertSame('clientes-crud', $response['programCode']);
        self::assertSame('Clientes', $response['programTitle']);
        self::assertSame('cadastros.clientes', $response['context']['programScreenId']);
    }

    private function contextPayload(): array
    {
        return [
            'programId' => 'clientes-crud',
            'programCode' => 'clientes-crud',
            'programTitle' => 'Clientes',
            'programScreenId' => 'cadastros.clientes',
            'programType' => 'crud',
            'moduleId' => 'operacional',
            'currentProgram' => [
                'id' => 'clientes-crud',
                'code' => 'clientes-crud',
                'title' => 'Clientes',
                'screenId' => 'cadastros.clientes',
                'type' => 'crud',
                'moduleId' => 'operacional',
            ],
        ];
    }

    private function createHandler(?HomeSupportService $support = null): HomeRuntimeHandler
    {
        return new HomeRuntimeHandler(
            $this->createStub(RuntimeNotificationService::class),
            $support ?? $this->createStub(HomeSupportService::class),
        );
    }
}
