<?php

namespace App\Tests\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeEndpoint;
use App\Entity\ScreenDefinition;
use App\Entity\RuntimeUserSession;
use App\Runtime\PermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PermissionResolverTest extends TestCase
{
    public function testScreenRequiresConfiguredGroup(): void
    {
        $screen = (new ScreenDefinition())
            ->setScreenId('cadastros.clientes')
            ->setStatus('published')
            ->setDefinition([
                'security' => [
                    'userGroups' => ['vendas'],
                ],
            ]);

        self::assertTrue($this->resolver(['vendas'], [])->canReadScreen($screen));
        self::assertFalse($this->resolver(['financeiro'], [])->canReadScreen($screen));
    }

    public function testEndpointRequiresExplicitPermission(): void
    {
        $endpoint = (new RuntimeEndpoint())
            ->setScreenId('cadastros.clientes')
            ->setEndpointId('delete')
            ->setHandler('entity.crud')
            ->setPermission('clientes.delete')
            ->setEnabled(true);

        self::assertFalse($this->resolver(['vendas'], ['clientes.read'])->canExecuteEndpoint($endpoint));
        self::assertTrue($this->resolver(['vendas'], ['clientes.delete'])->canExecuteEndpoint($endpoint));
    }

    public function testEndpointDerivesPermissionWhenEndpointFieldIsEmpty(): void
    {
        $endpoint = (new RuntimeEndpoint())
            ->setScreenId('cadastros.clientes')
            ->setEndpointId('read')
            ->setHandler('entity.crud')
            ->setEnabled(true)
            ->setConfig([
                'entityCode' => 'cliente',
                'operation' => 'read',
                'programId' => 'clientes-crud',
            ]);

        self::assertTrue($this->resolver(['vendas'], ['clientes.read'])->canExecuteEndpoint($endpoint));
        self::assertFalse($this->resolver(['vendas'], ['clientes.edit'])->canExecuteEndpoint($endpoint));
    }

    public function testDefinitionPermissionsAreFilteredForCurrentUser(): void
    {
        $definition = [
            'pageType' => 'crud',
            'screenId' => 'cadastros.clientes',
            'runtime' => [
                'entityCode' => 'cliente',
                'programId' => 'clientes-crud',
            ],
            'program' => [
                'id' => 'clientes-crud',
                'entity' => 'clientes',
            ],
            'permissions' => [
                'read' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
                'saveLayout' => true,
            ],
        ];

        $filtered = $this->resolver(['vendas'], ['clientes.read', 'clientes.edit', 'user.preferences'])
            ->applyDefinitionPermissions($definition);

        self::assertTrue($filtered['permissions']['read']);
        self::assertFalse($filtered['permissions']['create']);
        self::assertTrue($filtered['permissions']['edit']);
        self::assertFalse($filtered['permissions']['delete']);
        self::assertTrue($filtered['permissions']['saveLayout']);
    }

    public function testDefinitionPermissionsRespectDeniedPermission(): void
    {
        $definition = [
            'pageType' => 'crud',
            'screenId' => 'cadastros.clientes',
            'runtime' => [
                'entityCode' => 'cliente',
                'programId' => 'clientes-crud',
            ],
            'program' => [
                'id' => 'clientes-crud',
                'entity' => 'clientes',
            ],
            'permissions' => [
                'read' => true,
                'edit' => true,
                'delete' => true,
            ],
        ];

        $filtered = $this->resolverFromSession(['vendas'], [
            'clientes.read' => true,
            'clientes.delete' => false,
        ])->applyDefinitionPermissions($definition);

        self::assertTrue($filtered['permissions']['read']);
        self::assertFalse($filtered['permissions']['edit']);
        self::assertFalse($filtered['permissions']['delete']);
    }

    public function testDefinitionPermissionsRespectNestedDeniedPermission(): void
    {
        $definition = [
            'pageType' => 'crud',
            'screenId' => 'cadastros.clientes',
            'runtime' => [
                'entityCode' => 'cliente',
                'programId' => 'clientes-crud',
            ],
            'program' => [
                'id' => 'clientes-crud',
                'entity' => 'clientes',
            ],
            'permissions' => [
                'read' => true,
                'edit' => true,
                'delete' => true,
            ],
        ];

        $filtered = $this->resolverFromSession(['vendas'], [
            'clientes' => [
                'read' => true,
                'delete' => false,
            ],
            'admin' => [
                'read' => true,
            ],
        ])->applyDefinitionPermissions($definition);

        self::assertTrue($filtered['permissions']['read']);
        self::assertFalse($filtered['permissions']['edit']);
        self::assertFalse($filtered['permissions']['delete']);
    }

    public function testNestedPermissionDenialWinsOverNestedAllow(): void
    {
        $resolver = $this->resolverFromSession(['vendas'], [
            'clientes' => [
                '*' => true,
                'delete' => false,
            ],
        ]);

        self::assertFalse($resolver->hasPermission('clientes.delete'));
        self::assertTrue($resolver->hasPermission('clientes.read'));
    }

    public function testDeniedPermissionWinsOverAllowedPermission(): void
    {
        $endpoint = (new RuntimeEndpoint())
            ->setScreenId('cadastros.clientes')
            ->setEndpointId('delete')
            ->setHandler('entity.crud')
            ->setPermission('clientes.delete')
            ->setEnabled(true);

        $resolver = $this->resolverFromSession(['vendas'], [
            'clientes.*' => true,
            'clientes.delete' => false,
        ]);

        self::assertFalse($resolver->canExecuteEndpoint($endpoint));
    }

    public function testHomeProgramsAreFilteredByPermission(): void
    {
        $definition = [
            'pageType' => 'home',
            'permissions' => [
                'home.read' => true,
                'clientes.read' => true,
                'admin.read' => true,
            ],
            'programs' => [
                ['id' => 'painel', 'title' => 'Painel', 'permission' => 'home.read'],
                ['id' => 'clientes-crud', 'title' => 'Clientes', 'permission' => 'clientes.read'],
                ['id' => 'admin-parametros', 'title' => 'Parametros', 'permission' => 'admin.read'],
            ],
            'navigation' => [
                'groups' => [
                    [
                        'id' => 'principal',
                        'items' => [
                            ['programId' => 'painel'],
                            ['programId' => 'clientes-crud'],
                            ['programId' => 'admin-parametros'],
                        ],
                    ],
                ],
            ],
        ];

        $filtered = $this->resolver(['vendas'], ['home.read', 'clientes.read'])
            ->applyDefinitionPermissions($definition);

        self::assertTrue($filtered['permissions']['home.read']);
        self::assertTrue($filtered['permissions']['clientes.read']);
        self::assertFalse($filtered['permissions']['admin.read']);
        self::assertSame(['painel', 'clientes-crud'], array_column($filtered['programs'], 'id'));
        self::assertSame(
            ['painel', 'clientes-crud'],
            array_column($filtered['navigation']['groups'][0]['items'], 'programId'),
        );
    }

    /**
     * @param string[] $groups
     * @param string[] $permissions
     */
    private function resolver(array $groups, array $permissions): PermissionResolver
    {
        $request = Request::create('/api/runtime/screens/cadastros.clientes', 'GET', [
            'runtimeGroups' => implode(',', $groups),
            'runtimePermissions' => implode(',', $permissions),
        ]);
        $stack = new RequestStack();
        $stack->push($request);

        return new PermissionResolver($stack);
    }

    /**
     * @param string[] $groups
     * @param array<string, mixed> $permissions
     */
    private function resolverFromSession(array $groups, array $permissions): PermissionResolver
    {
        $request = Request::create('/api/runtime/screens/cadastros.clientes', 'GET', [
            'runtimeGroups' => implode(',', $groups),
        ]);
        $session = (new RuntimeUserSession())
            ->setTenantId('default')
            ->setUserId('user-1')
            ->setUserName('Usuario Teste')
            ->setSessionId('sess-test')
            ->setSessionProperties([])
            ->setPermissionSnapshot([
                'groups' => $groups,
                'permissions' => $permissions,
                'user' => [
                    'id' => 'user-1',
                    'name' => 'Usuario Teste',
                ],
            ]);

        $authenticatedSessionResolver = $this->createStub(AuthenticatedSessionResolver::class);
        $authenticatedSessionResolver->method('resolve')->willReturn($session);

        $stack = new RequestStack();
        $stack->push($request);

        return new PermissionResolver($stack, $authenticatedSessionResolver);
    }
}
