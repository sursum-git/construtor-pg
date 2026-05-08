<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeSituationService;
use App\Runtime\RuntimeTransactionService;
use App\Runtime\RuntimeValidationException;
use PHPUnit\Framework\TestCase;

class RuntimeSituationServiceTest extends TestCase
{
    public function testAppliesInitialSituationOnCreate(): void
    {
        $service = new RuntimeSituationService($this->createStub(RuntimeTransactionService::class));
        $values = $service->applyCreateDefaults($this->definition(), ['nome' => 'Cliente']);

        self::assertSame('EM_DIGITACAO', $values['situacao']);
    }

    public function testBlocksUnknownSituation(): void
    {
        $service = new RuntimeSituationService($this->createStub(RuntimeTransactionService::class));

        $this->expectException(RuntimeValidationException::class);
        $this->expectExceptionMessage('Situacao invalida para a entidade.');

        $service->validateCreate($this->definition(), ['situacao' => 'CANCELADO'], 'create');
    }

    public function testBlocksTransitionNotConfigured(): void
    {
        $service = new RuntimeSituationService($this->createStub(RuntimeTransactionService::class));

        $this->expectException(RuntimeValidationException::class);
        $this->expectExceptionMessage('Transicao de situacao nao permitida.');

        $service->validateUpdate(
            $this->definition(),
            ['situacao' => 'EM_DIGITACAO'],
            ['situacao' => 'APROVADO'],
            'update',
        );
    }

    public function testValidatesTransitionRequiredFields(): void
    {
        $service = new RuntimeSituationService($this->createStub(RuntimeTransactionService::class));

        $this->expectException(RuntimeValidationException::class);
        $this->expectExceptionMessage('Existem regras pendentes para mudar a situacao.');

        $service->validateUpdate(
            $this->definition(),
            ['situacao' => 'EM_DIGITACAO', 'total' => null],
            ['situacao' => 'COMPLETO'],
            'update',
        );
    }

    public function testDecoratesRuntimeSituation(): void
    {
        $service = new RuntimeSituationService($this->createStub(RuntimeTransactionService::class));
        $row = $service->decorateRow($this->definition(), [
            'id' => 1,
            'situacao' => 'APROVADO',
            '_runtime' => ['version' => 'abc'],
        ]);

        self::assertSame('APROVADO', $row['_runtime']['situation']['code']);
        self::assertSame('Aprovado', $row['_runtime']['situation']['label']);
        self::assertTrue($row['_runtime']['situation']['final']);
    }

    private function definition(): array
    {
        return [
            'entityCode' => 'pedido',
            'fields' => [
                'id' => ['label' => 'ID', 'writable' => false],
                'situacao' => ['label' => 'Situacao', 'writable' => true],
                'total' => ['label' => 'Total', 'writable' => true],
            ],
            'situation' => [
                'enabled' => true,
                'field' => 'situacao',
                'initial' => 'EM_DIGITACAO',
                'situations' => [
                    'EM_DIGITACAO' => ['code' => 'EM_DIGITACAO', 'label' => 'Em digitacao', 'initial' => true, 'final' => false],
                    'COMPLETO' => ['code' => 'COMPLETO', 'label' => 'Completo', 'initial' => false, 'final' => false],
                    'APROVADO' => ['code' => 'APROVADO', 'label' => 'Aprovado', 'initial' => false, 'final' => true],
                ],
                'transitions' => [
                    [
                        'from' => null,
                        'to' => 'EM_DIGITACAO',
                        'actionId' => 'create',
                        'guardConfig' => [],
                        'effects' => [],
                    ],
                    [
                        'from' => 'EM_DIGITACAO',
                        'to' => 'COMPLETO',
                        'actionId' => 'update',
                        'guardConfig' => [
                            'requiredFields' => ['total'],
                        ],
                        'effects' => [],
                    ],
                    [
                        'from' => 'COMPLETO',
                        'to' => 'APROVADO',
                        'actionId' => 'update',
                        'guardConfig' => [],
                        'effects' => [],
                    ],
                ],
            ],
        ];
    }
}
