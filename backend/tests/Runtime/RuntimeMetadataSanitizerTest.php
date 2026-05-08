<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeMetadataSanitizer;
use PHPUnit\Framework\TestCase;

class RuntimeMetadataSanitizerTest extends TestCase
{
    public function testCrudDefinitionUsesEndpointIdsAndRemovesDangerousKeys(): void
    {
        $definition = [
            'schemaVersion' => '1.0',
            'pageType' => 'crud',
            'program' => ['id' => 'cadastros.clientes'],
            'dataSource' => [
                'api' => [
                    'read' => ['url' => '/api/clientes', 'method' => 'GET', 'template' => '#= nome #'],
                    'create' => ['endpointId' => 'create', 'method' => 'POST'],
                ],
            ],
            'crud' => [
                'grid' => [
                    'mobile' => [
                        'mode' => 'template',
                        'template' => [
                            'titleField' => 'nome',
                            'fields' => ['email'],
                        ],
                    ],
                ],
                'form' => [
                    'logs' => ['url' => 'docs/logs.html'],
                ],
            ],
        ];

        $sanitized = (new RuntimeMetadataSanitizer())->sanitize($definition);

        self::assertSame('read', $sanitized['dataSource']['api']['read']['endpointId']);
        self::assertSame('POST', $sanitized['dataSource']['api']['read']['method']);
        self::assertArrayNotHasKey('url', $sanitized['dataSource']['api']['read']);
        self::assertArrayNotHasKey('template', $sanitized['dataSource']['api']['read']);
        self::assertSame(['email'], $sanitized['crud']['grid']['mobile']['template']['fields']);
        self::assertSame('form.logs', $sanitized['crud']['form']['logs']['documentId']);
        self::assertArrayNotHasKey('url', $sanitized['crud']['form']['logs']);
    }

    public function testHomeCrudProgramUsesScreenId(): void
    {
        $definition = [
            'schemaVersion' => '1.0',
            'pageType' => 'home',
            'app' => [
                'id' => 'home',
                'title' => 'Home',
                'logo' => ['url' => 'public/assets/company-logo.svg'],
            ],
            'layout' => [
                'initialProgramId' => 'painel',
                'appbar' => [
                    'subscriberSwitch' => [
                        'programId' => 'troca-assinante',
                        'endpoints' => [
                            'change' => ['url' => '/api/home/subscribers/change'],
                        ],
                    ],
                    'alerts' => [
                        'endpoints' => [
                            'list' => '/api/home/alerts',
                        ],
                    ],
                ],
            ],
            'navigation' => [
                'groups' => [
                    [
                        'id' => 'principal',
                        'title' => 'Principal',
                        'items' => [
                            ['programId' => 'painel', 'title' => 'Painel'],
                            ['programId' => 'clientes-crud', 'title' => 'Clientes'],
                            ['programId' => 'exemplos', 'title' => 'Exemplos'],
                        ],
                    ],
                ],
            ],
            'programs' => [
                [
                    'id' => 'painel',
                    'title' => 'Painel',
                    'type' => 'html',
                    'html' => '<section>Painel</section>',
                ],
                [
                    'id' => 'clientes-crud',
                    'title' => 'Clientes',
                    'type' => 'crud',
                    'definitionUrl' => 'examples/clientes.crud.json',
                    'openUrl' => 'index.html',
                ],
                [
                    'id' => 'exemplos',
                    'title' => 'Exemplos',
                    'type' => 'iframe',
                    'url' => 'exemplos.html',
                ],
            ],
        ];

        $sanitized = (new RuntimeMetadataSanitizer())->sanitize($definition);

        self::assertCount(1, $sanitized['programs']);
        self::assertSame('/public/assets/company-logo.svg', $sanitized['app']['logo']['url']);
        self::assertSame('clientes-crud', $sanitized['programs'][0]['id']);
        self::assertSame('cadastros.clientes', $sanitized['programs'][0]['screenId']);
        self::assertArrayNotHasKey('definitionUrl', $sanitized['programs'][0]);
        self::assertArrayNotHasKey('openUrl', $sanitized['programs'][0]);
        self::assertSame('clientes-crud', $sanitized['layout']['initialProgramId']);
        self::assertSame([
            ['programId' => 'clientes-crud', 'title' => 'Clientes'],
        ], $sanitized['navigation']['groups'][0]['items']);
        self::assertArrayNotHasKey('programId', $sanitized['layout']['appbar']['subscriberSwitch']);
        self::assertSame('home.subscriber.change', $sanitized['layout']['appbar']['subscriberSwitch']['endpoints']['change']['endpointId']);
        self::assertSame('home.alerts.list', $sanitized['layout']['appbar']['alerts']['endpoints']['list']['endpointId']);
        self::assertArrayNotHasKey('url', $sanitized['layout']['appbar']['alerts']['endpoints']['list']);
    }
}
