<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeAsyncJobService;
use App\Runtime\RuntimeJobEnqueueService;
use App\Runtime\RuntimeValidationException;
use PHPUnit\Framework\TestCase;

class RuntimeJobEnqueueServiceTest extends TestCase
{
    public function testEnqueuesConfiguredManualJob(): void
    {
        $asyncJobs = $this->createMock(RuntimeAsyncJobService::class);
        $asyncJobs->expects(self::once())
            ->method('schedule')
            ->with(
                'cliente.whatsapp_welcome',
                [
                    'clienteId' => 1,
                    'nome' => 'Ana',
                    'telefone' => '(85) 98888-1001',
                ],
                self::callback(function (array $options): bool {
                    return $options['entityCode'] === 'cliente'
                        && $options['programId'] === 'clientes-crud'
                        && $options['recordId'] === 1
                        && $options['actionId'] === 'sendWhatsapp'
                        && $options['message'] === 'WhatsApp agendado.';
                }),
            );

        $response = (new RuntimeJobEnqueueService($asyncJobs))->handle('cadastros.clientes', 'sendWhatsapp', $this->config(), [
            'id' => 1,
            'entityCode' => 'cliente',
            'programId' => 'clientes-crud',
            'actionId' => 'sendWhatsapp',
            'values' => [
                'id' => 1,
                'nome' => 'Ana',
                'telefone' => '(85) 98888-1001',
            ],
            'record' => [
                'id' => 1,
                'nome' => 'Ana',
                'telefone' => '(85) 98888-1001',
            ],
        ]);

        self::assertSame(['ok' => true, 'queued' => 1], $response);
    }

    public function testRequiredPayloadBlocksManualJob(): void
    {
        $asyncJobs = $this->createMock(RuntimeAsyncJobService::class);
        $asyncJobs->expects(self::never())->method('schedule');

        $this->expectException(RuntimeValidationException::class);
        (new RuntimeJobEnqueueService($asyncJobs))->handle('cadastros.clientes', 'sendWhatsapp', $this->config(), [
            'id' => 1,
            'values' => [
                'id' => 1,
                'nome' => 'Ana',
                'telefone' => '',
            ],
            'record' => [
                'id' => 1,
                'nome' => 'Ana',
            ],
        ]);
    }

    private function config(): array
    {
        return [
            'entityCode' => 'cliente',
            'actionId' => 'sendWhatsapp',
            'programId' => 'clientes-crud',
            'jobs' => [
                [
                    'id' => 'cliente-whatsapp-welcome',
                    'type' => 'cliente.whatsapp_welcome',
                    'mode' => 'async',
                    'required' => [
                        [
                            'path' => 'values.telefone',
                            'field' => 'telefone',
                            'message' => 'Informe o telefone do cliente para enviar WhatsApp.',
                        ],
                    ],
                    'payload' => [
                        'clienteId' => 'record.id',
                        'nome' => 'record.nome',
                        'telefone' => 'values.telefone',
                    ],
                    'queuedMessage' => 'WhatsApp agendado.',
                ],
            ],
        ];
    }
}
