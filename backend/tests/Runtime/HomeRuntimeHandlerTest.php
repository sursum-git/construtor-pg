<?php

namespace App\Tests\Runtime;

use App\Runtime\HomeRuntimeHandler;
use PHPUnit\Framework\TestCase;

class HomeRuntimeHandlerTest extends TestCase
{
    public function testAiSendUsesProgramContext(): void
    {
        $response = (new HomeRuntimeHandler())->handle('aiSend', [
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
        $response = (new HomeRuntimeHandler())->handle('supportCreateRequest', [
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
}
