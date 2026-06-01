<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeExternalJsonClient;
use App\Runtime\RuntimeTransactionService;
use PHPUnit\Framework\TestCase;

class RuntimeApiEntityActionServiceTest extends TestCase
{
    public function testReadEnrichesRowsWithBatchLookupAndDoesNotRepeatCustomerFetch(): void
    {
        $entity = (new BuilderEntity())
            ->setCode('pedidos_api')
            ->setName('Pedidos API')
            ->setEntityType('api')
            ->setMetadata([
                'apiSource' => [
                    'authHeaders' => [],
                    'timeoutSeconds' => 10,
                    'listEndpoint' => [
                        'url' => '/externo/pedidos',
                        'method' => 'POST',
                        'headers' => [],
                        'queryParams' => [],
                        'bodyTemplate' => null,
                    ],
                    'listResponse' => [
                        'itemsPath' => 'items',
                        'totalPath' => 'total',
                    ],
                    'operations' => [
                        [
                            'code' => 'clientes_batch',
                            'type' => 'custom',
                            'method' => 'POST',
                            'path' => '/externo/clientes/lote',
                            'headers' => [],
                            'queryParams' => [],
                            'bodyTemplate' => ['ids' => []],
                            'itemsPath' => 'items',
                            'itemPath' => null,
                            'totalPath' => null,
                        ],
                    ],
                ],
            ]);

        $entity
            ->addField($this->field('id', 'Id', 'integer', 1, true, ['api' => ['jsonPath' => 'id']]))
            ->addField($this->field('cliente_id', 'Cliente', 'integer', 2, false, ['api' => ['jsonPath' => 'cliente_id']]))
            ->addField($this->field('cliente_nome', 'Nome do cliente', 'string', 3, false, [
                'api' => [
                    'jsonPath' => 'cliente_nome',
                    'lookupResolver' => [
                        'operationCode' => 'clientes_batch',
                        'sourceField' => 'cliente_id',
                        'requestParam' => 'ids',
                        'mode' => 'batch',
                        'responseItemsPath' => 'items',
                        'matchField' => 'id',
                        'valuePath' => 'nome',
                    ],
                ],
            ]))
            ->addField($this->field('valor_total', 'Valor total', 'decimal', 4, false, ['api' => ['jsonPath' => 'valor_total']]));

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);

        $transactions = $this->createStub(RuntimeTransactionService::class);

        $externalClient = $this->createMock(RuntimeExternalJsonClient::class);
        $requestLog = [];
        $externalClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $url, string $method, array $headers, mixed $payload) use (&$requestLog): array {
                $requestLog[] = ['url' => $url, 'method' => $method, 'payload' => $payload];
                if ($url === '/externo/pedidos') {
                    return [
                        'status' => 200,
                        'body' => [
                            'items' => [
                                ['id' => 10, 'cliente_id' => 1, 'valor_total' => 120.50],
                                ['id' => 11, 'cliente_id' => 1, 'valor_total' => 80.00],
                                ['id' => 12, 'cliente_id' => 2, 'valor_total' => 90.00],
                            ],
                            'total' => 3,
                        ],
                    ];
                }
                if ($url === '/externo/clientes/lote') {
                    self::assertSame('POST', $method);
                    self::assertSame(['ids' => [1, 2]], $payload);

                    return [
                        'status' => 200,
                        'body' => [
                            'items' => [
                                ['id' => 1, 'nome' => 'Cliente Acme'],
                                ['id' => 2, 'nome' => 'Cliente Beta'],
                            ],
                        ],
                    ];
                }

                self::fail('URL inesperada: ' . $url);
            });

        $service = new RuntimeApiEntityActionService($entities, $transactions, $externalClient);
        $result = $service->handle('consultas.api.pedidos', 'read', [
            'entityCode' => 'pedidos_api',
            'operation' => 'read',
        ], [
            'take' => 20,
            'skip' => 0,
        ]);

        self::assertSame(3, $result['total']);
        self::assertSame('Cliente Acme', $result['data'][0]['cliente_nome']);
        self::assertSame('Cliente Acme', $result['data'][1]['cliente_nome']);
        self::assertSame('Cliente Beta', $result['data'][2]['cliente_nome']);
        self::assertCount(2, $requestLog);
    }

    public function testGetEnrichesSingleRecordWithPerValueLookup(): void
    {
        $entity = (new BuilderEntity())
            ->setCode('pedidos_api')
            ->setName('Pedidos API')
            ->setEntityType('api')
            ->setMetadata([
                'apiSource' => [
                    'authHeaders' => [],
                    'timeoutSeconds' => 10,
                    'listEndpoint' => [
                        'url' => '/externo/pedidos',
                        'method' => 'POST',
                        'headers' => [],
                        'queryParams' => [],
                        'bodyTemplate' => null,
                    ],
                    'listResponse' => [
                        'itemsPath' => 'items',
                        'totalPath' => 'total',
                    ],
                    'detailEndpoint' => [
                        'url' => '/externo/pedidos/{id}',
                        'method' => 'GET',
                        'headers' => [],
                        'queryParams' => [],
                        'bodyTemplate' => null,
                    ],
                    'detailResponse' => [
                        'itemPath' => '$',
                    ],
                    'operations' => [
                        [
                            'code' => 'cliente_detalhe',
                            'type' => 'detail',
                            'method' => 'GET',
                            'path' => '/externo/clientes/{id}',
                            'headers' => [],
                            'queryParams' => [],
                            'bodyTemplate' => null,
                            'itemPath' => '$',
                        ],
                    ],
                ],
            ]);

        $entity
            ->addField($this->field('id', 'Id', 'integer', 1, true, ['api' => ['jsonPath' => 'id']]))
            ->addField($this->field('cliente_id', 'Cliente', 'integer', 2, false, ['api' => ['jsonPath' => 'cliente_id']]))
            ->addField($this->field('cliente_nome', 'Nome do cliente', 'string', 3, false, [
                'api' => [
                    'jsonPath' => 'cliente_nome',
                    'lookupResolver' => [
                        'operationCode' => 'cliente_detalhe',
                        'sourceField' => 'cliente_id',
                        'requestParam' => 'id',
                        'mode' => 'per_value',
                        'responseItemPath' => '$',
                        'valuePath' => 'nome',
                    ],
                ],
            ]));

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);

        $transactions = $this->createStub(RuntimeTransactionService::class);

        $externalClient = $this->createMock(RuntimeExternalJsonClient::class);
        $externalClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $url): array {
                if ($url === '/externo/pedidos/10?id=10') {
                    return [
                        'status' => 200,
                        'body' => ['id' => 10, 'cliente_id' => 9],
                    ];
                }
                if ($url === '/externo/clientes/9?id=9') {
                    return [
                        'status' => 200,
                        'body' => ['id' => 9, 'nome' => 'Cliente Delta'],
                    ];
                }

                self::fail('URL inesperada: ' . $url);
            });

        $service = new RuntimeApiEntityActionService($entities, $transactions, $externalClient);
        $result = $service->handle('consultas.api.pedidos', 'get', [
            'entityCode' => 'pedidos_api',
            'operation' => 'get',
        ], [
            'id' => 10,
        ]);

        self::assertSame(10, $result['id']);
        self::assertSame(9, $result['cliente_id']);
        self::assertSame('Cliente Delta', $result['cliente_nome']);
    }

    private function field(string $code, string $label, string $dataType, int $position, bool $primaryKey, array $options): BuilderField
    {
        return (new BuilderField())
            ->setCode($code)
            ->setLabel($label)
            ->setDataType($dataType)
            ->setPosition($position)
            ->setPrimaryKey($primaryKey)
            ->setOptions($options);
    }
}
