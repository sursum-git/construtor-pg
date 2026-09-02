<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Odoo\OdooClient;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\ProgramOverlayService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProgramBuilderServiceMasterDetailTest extends TestCase
{
    public function testGenerateMasterDetailDefinitionBuildsGraph(): void
    {
        $definition = $this->invokePrivateMixed($this->service(), 'generateMasterDetailDefinition', [[
            'pageType' => 'master_detail',
            'programCode' => 'vd0101',
            'programTitle' => 'Pedido de venda',
            'screenId' => 'vendas.pedidos',
            'module' => 'vendas',
            'permissionPrefix' => 'vendas.pedido',
            'version' => '1.0.0',
            '_entity' => $this->pedidoEntity(),
            'masterDetailConfig' => $this->validMasterDetailConfig(),
        ]]);

        self::assertSame('master_detail', $definition['pageType']);
        self::assertSame('pedido_venda', $definition['master']['entity']);
        self::assertSame('pedido_id', $definition['details'][0]['parentField']);
        self::assertSame('createGraph', $definition['createFlow']['endpointId']);
        self::assertArrayNotHasKey('url', $definition['createFlow']);
    }

    #[DataProvider('invalidMasterDetailConfigs')]
    public function testNormalizeMasterDetailConfigRejectsInvalidReferences(array $config, string $errorCode): void
    {
        try {
            $this->invokePrivateMixed($this->service(), 'normalizeMasterDetailBuilderConfig', [$config, $this->pedidoEntity()]);
            self::fail('A configuracao mestre-detalhe invalida deveria ser rejeitada.');
        } catch (RuntimeHttpException $error) {
            self::assertSame($errorCode, $error->getErrorCode());
            self::assertSame(422, $error->getStatusCode());
            self::assertNotSame([], $error->getDetails());
        }
    }

    public static function invalidMasterDetailConfigs(): iterable
    {
        $valid = self::baseMasterDetailConfig();

        $duplicate = $valid;
        $duplicate['details'][] = $valid['details'][0];
        yield 'filha repetida' => [$duplicate, 'PROGRAM_BUILDER_MASTER_DETAIL_DETAIL_DUPLICATE'];

        $invalidParent = $valid;
        $invalidParent['details'][0]['parentField'] = 'pedido_inexistente_id';
        yield 'fk inexistente' => [$invalidParent, 'PROGRAM_BUILDER_MASTER_DETAIL_PARENT_FIELD_INVALID'];

        $invalidField = $valid;
        $invalidField['details'][0]['displayFields'] = ['produto_inexistente'];
        yield 'campo ou total invalido' => [$invalidField, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];

        $withoutGraph = $valid;
        $withoutGraph['createFlow'] = ['mode' => 'draftWithChildren'];
        yield 'fluxo conjunto sem endpoint' => [$withoutGraph, 'PROGRAM_BUILDER_MASTER_DETAIL_CREATE_GRAPH_REQUIRED'];
    }

    private function service(): ProgramBuilderService
    {
        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturnCallback(function (array $criteria): ?BuilderEntity {
            return match ($criteria['code'] ?? null) {
                'pedido_venda' => $this->pedidoEntity(),
                'pedido_item' => $this->pedidoItemEntity(),
                default => null,
            };
        });

        return new ProgramBuilderService(
            $entities,
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $this->createStub(BuilderModuleRepository::class),
            $this->createStub(BuilderFieldRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(ProgramRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
            $this->createStub(PermissionResolver::class),
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );
    }

    private function validMasterDetailConfig(): array
    {
        return self::baseMasterDetailConfig();
    }

    private static function baseMasterDetailConfig(): array
    {
        return [
            'masterEntityCode' => 'pedido_venda',
            'createFlow' => [
                'mode' => 'draftWithChildren',
                'endpointId' => 'createGraph',
            ],
            'details' => [[
                'entityCode' => 'pedido_item',
                'title' => 'Itens',
                'parentField' => 'pedido_id',
                'displayFields' => ['produto', 'quantidade', 'valor_total'],
                'totals' => [[
                    'field' => 'valor_total',
                    'label' => 'Total dos itens',
                    'type' => 'currency',
                ]],
            ]],
        ];
    }

    private function pedidoEntity(): BuilderEntity
    {
        return $this->entity('pedido_venda', 'Pedido de venda', [
            $this->field('id', 'ID', 'integer', 1, true),
            $this->field('numero', 'Numero', 'string', 2),
            $this->field('cliente', 'Cliente', 'string', 3),
        ]);
    }

    private function pedidoItemEntity(): BuilderEntity
    {
        return $this->entity('pedido_item', 'Item do pedido', [
            $this->field('id', 'ID', 'integer', 1, true),
            $this->field('pedido_id', 'Pedido', 'integer', 2),
            $this->field('produto', 'Produto', 'string', 3),
            $this->field('quantidade', 'Quantidade', 'decimal', 4),
            $this->field('valor_total', 'Valor total', 'currency', 5),
        ]);
    }

    private function entity(string $code, string $name, array $fields): BuilderEntity
    {
        $entity = (new BuilderEntity())
            ->setCode($code)
            ->setName($name)
            ->setEntityType('persistence')
            ->setTableName('t_' . $code);
        foreach ($fields as $field) {
            $entity->addField($field);
        }

        return $entity;
    }

    private function field(string $code, string $label, string $type, int $position, bool $primaryKey = false): BuilderField
    {
        return (new BuilderField())
            ->setCode($code)
            ->setLabel($label)
            ->setDataType($type)
            ->setPosition($position)
            ->setPrimaryKey($primaryKey);
    }

    private function invokePrivateMixed(object $target, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
