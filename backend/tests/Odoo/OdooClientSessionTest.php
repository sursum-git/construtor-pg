<?php

namespace App\Tests\Odoo;

use App\Odoo\OdooClient;
use App\Odoo\OdooExecutionContext;
use PHPUnit\Framework\TestCase;

class OdooClientSessionTest extends TestCase
{
    public function testSessionReusesAuthenticationAcrossReadOperations(): void
    {
        $client = new class extends OdooClient {
            public int $authenticateCalls = 0;

            public function authenticate(array $config): int
            {
                $this->authenticateCalls++;

                return 7;
            }

            public function normalizeConfig(array $config): array
            {
                return [
                    'baseUrl' => 'http://odoo.local',
                    'database' => 'odoo_demo',
                    'login' => 'admin',
                    'secretMode' => 'password',
                    'secretValue' => 'admin123',
                    'transport' => 'jsonrpc',
                    'model' => 'res.partner',
                    'defaultContext' => [],
                    'defaultDomain' => [],
                    'defaultOrder' => 'id asc',
                    'defaultLimit' => 80,
                    'timeoutSeconds' => 10,
                ];
            }

            public function executeKwWithSession(OdooExecutionContext $session, string $model, string $method, array $args = [], array $kwargs = []): mixed
            {
                return match ($method) {
                    'search_read' => [
                        ['id' => 1, 'name' => 'Azure Interior'],
                        ['id' => 2, 'name' => 'Blue Ocean'],
                        ['id' => 3, 'name' => 'Casa Lima'],
                    ],
                    'search_count' => 3,
                    'read' => [['id' => 1, 'name' => 'Azure Interior']],
                    default => throw new \LogicException('Metodo inesperado no teste: ' . $method),
                };
            }
        };

        $session = $client->openSession(['model' => 'res.partner']);
        $records = $client->searchReadWithSession($session, [
            'domain' => [],
            'fields' => ['id', 'name'],
            'offset' => 0,
            'limit' => 10,
            'order' => 'id asc',
            'context' => [],
        ]);
        $total = $client->searchCountWithSession($session, [], []);
        $detail = $client->readWithSession($session, [1], ['id', 'name'], []);

        self::assertSame(7, $session->getUid());
        self::assertCount(3, $records);
        self::assertSame(3, $total);
        self::assertSame('Azure Interior', $detail[0]['name'] ?? null);
        self::assertSame(1, $client->authenticateCalls);
    }
}
