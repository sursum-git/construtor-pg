<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeMasterDetailActionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class RuntimeMasterDetailActionServiceTest extends TestCase
{
    public function testCreateGraphPersistsMasterAndChildrenInOneTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation($connection));

        $calls = [];
        $entities = $this->createMock(RuntimeEntityActionService::class);
        $entities->expects(self::exactly(3))
            ->method('handle')
            ->willReturnCallback(static function (string $screenId, string $endpointId, array $config, array $payload) use (&$calls): array {
                $calls[] = compact('screenId', 'endpointId', 'config', 'payload');
                return match ($endpointId) {
                    'master.create' => ['id' => 45, 'numero' => 'PV-45'],
                    'detail.pedido_item.create' => ['id' => 101] + $payload['values'],
                    'detail.pedido_parcela.create' => ['id' => 201] + $payload['values'],
                };
            });

        $service = new RuntimeMasterDetailActionService($connection, $entities);
        $result = $service->handle('vendas.pedidos', 'createGraph', [
            'masterEntityCode' => 'pedido_venda',
            'masterIdField' => 'id',
            'details' => [
                ['id' => 'pedido_item', 'entityCode' => 'pedido_item', 'parentField' => 'pedido_id'],
                ['id' => 'pedido_parcela', 'entityCode' => 'pedido_parcela', 'parentField' => 'pedido_id'],
            ],
        ], [
            'master' => ['numero' => 'PV-45'],
            'details' => [
                'pedido_item' => [['produto' => 'Produto A']],
                'pedido_parcela' => [['numero' => 1]],
            ],
        ]);

        self::assertSame(45, $result['master']['id']);
        self::assertSame(45, $result['details']['pedido_item'][0]['pedido_id']);
        self::assertSame(45, $result['details']['pedido_parcela'][0]['pedido_id']);
        self::assertSame(['master.create', 'detail.pedido_item.create', 'detail.pedido_parcela.create'], array_column($calls, 'endpointId'));
        self::assertSame('pedido_venda', $calls[0]['config']['entityCode']);
        self::assertSame('create', $calls[0]['config']['operation']);
        self::assertSame('pedido_item', $calls[1]['config']['entityCode']);
        self::assertSame('pedido_item', $calls[1]['payload']['entityCode']);
        self::assertSame(45, $calls[1]['payload']['values']['pedido_id']);
    }
}
