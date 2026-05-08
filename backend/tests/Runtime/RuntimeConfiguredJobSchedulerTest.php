<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeAsyncJobService;
use App\Runtime\RuntimeBusinessRuleContext;
use App\Runtime\RuntimeConfiguredJobScheduler;
use PHPUnit\Framework\TestCase;

class RuntimeConfiguredJobSchedulerTest extends TestCase
{
    public function testSchedulesConfiguredAsyncJobFromEntityMetadata(): void
    {
        $jobs = $this->createMock(RuntimeAsyncJobService::class);
        $jobs->expects(self::once())
            ->method('schedule')
            ->with(
                'cliente.email_confirmation',
                [
                    'clienteId' => 10,
                    'nome' => 'Ana',
                    'email' => 'ana@empresa.test',
                ],
                self::callback(function (array $options): bool {
                    return $options['entityCode'] === 'cliente'
                        && $options['recordId'] === 10
                        && $options['actionId'] === 'create'
                        && $options['programId'] === 'clientes-crud'
                        && $options['message'] === 'E-mail de confirmacao agendado.';
                }),
            );

        (new RuntimeConfiguredJobScheduler($jobs))->scheduleAfterSuccess($this->context([
            'id' => 10,
            'nome' => 'Ana',
            'email' => 'ana@empresa.test',
        ]));
    }

    public function testDoesNotScheduleWhenConditionDoesNotMatch(): void
    {
        $jobs = $this->createMock(RuntimeAsyncJobService::class);
        $jobs->expects(self::never())->method('schedule');

        (new RuntimeConfiguredJobScheduler($jobs))->scheduleAfterSuccess($this->context([
            'id' => 10,
            'nome' => 'Ana',
            'email' => 'email-invalido',
        ]));
    }

    public function testEndpointJobCanOverrideEntityJobWithSameId(): void
    {
        $jobs = $this->createMock(RuntimeAsyncJobService::class);
        $jobs->expects(self::once())
            ->method('schedule')
            ->with(
                'cliente.email_confirmation',
                [
                    'clienteId' => 10,
                    'email' => 'ana@empresa.test',
                ],
                self::callback(fn (array $options): bool => $options['message'] === 'Mensagem do endpoint.'),
            );

        $context = $this->context([
            'id' => 10,
            'nome' => 'Ana',
            'email' => 'ana@empresa.test',
        ], [
            'jobs' => [
                [
                    'id' => 'cliente-email-confirmation',
                    'type' => 'cliente.email_confirmation',
                    'trigger' => 'after_success',
                    'mode' => 'async',
                    'operations' => ['create'],
                    'when' => ['path' => 'after.email', 'operator' => 'isEmail'],
                    'payload' => [
                        'clienteId' => 'after.id',
                        'email' => 'after.email',
                    ],
                    'queuedMessage' => 'Mensagem do endpoint.',
                ],
            ],
        ]);

        (new RuntimeConfiguredJobScheduler($jobs))->scheduleAfterSuccess($context);
    }

    private function context(array $after, array $endpointOverride = []): RuntimeBusinessRuleContext
    {
        $definition = [
            'entityCode' => 'cliente',
            'primaryKey' => 'id',
            'metadata' => [
                'screenId' => 'cadastros.clientes',
                'jobs' => [
                    [
                        'id' => 'cliente-email-confirmation',
                        'type' => 'cliente.email_confirmation',
                        'trigger' => 'after_success',
                        'mode' => 'async',
                        'operations' => ['create'],
                        'when' => [
                            'source' => 'after',
                            'field' => 'email',
                            'operator' => 'isEmail',
                        ],
                        'payload' => [
                            'clienteId' => 'after.id',
                            'nome' => 'after.nome',
                            'email' => 'after.email',
                        ],
                        'queuedMessage' => 'E-mail de confirmacao agendado.',
                    ],
                ],
            ],
        ];
        $payload = [
            'programId' => 'clientes-crud',
            '_runtimeEndpoint' => array_merge([
                'entityCode' => 'cliente',
                'operation' => 'create',
                'actionId' => 'create',
                'programId' => 'clientes-crud',
            ], $endpointOverride),
        ];

        return new RuntimeBusinessRuleContext($definition, 'create', 'create', $payload, [], [], $after);
    }
}
