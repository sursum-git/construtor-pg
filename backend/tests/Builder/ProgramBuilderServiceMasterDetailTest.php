<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Entity\BuilderProgramVersion;
use App\Entity\Program;
use App\Entity\ScreenDefinition;
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
    public function testSaveAndPublishMasterDetailKeepsConfigurationAndDefinition(): void
    {
        $module = (new BuilderModule())
            ->setCode('vendas')
            ->setName('Vendas')
            ->setAbbreviation('vd')
            ->setNumberStart(100)
            ->setNumberEnd(199);
        $master = $this->pedidoEntity();
        $detail = $this->pedidoItemEntity();
        $masterVersion = (new BuilderEntityVersion())->setBuilderEntityCode('pedido_venda');
        $this->setEntityId($masterVersion, 700);
        $savedVersion = null;
        $publishedProgram = null;
        $publishedScreen = null;

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($master, $detail): ?BuilderEntity {
            return match ($criteria['code'] ?? null) {
                'pedido_venda' => $master,
                'pedido_item' => $detail,
                default => null,
            };
        });

        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'vendas'] ? $module : null);

        $versions = $this->createStub(BuilderProgramVersionRepository::class);
        $versions->method('findOneBy')->willReturn(null);
        $versions->method('find')->willReturnCallback(static function (int $id) use (&$savedVersion): ?BuilderProgramVersion {
            return $id === 701 ? $savedVersion : null;
        });
        $versions->method('findByProgramCodeOrdered')->willReturnCallback(static function (string $programCode) use (&$savedVersion): array {
            return $programCode === 'vd0101' && $savedVersion ? [$savedVersion] : [];
        });

        $programs = $this->createStub(ProgramRepository::class);
        $programs->method('findOneBy')->willReturnCallback(static function () use (&$publishedProgram): ?Program {
            return $publishedProgram;
        });
        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findOneBy')->willReturn(null);
        $entityVersions = $this->createStub(BuilderEntityVersionRepository::class);
        $entityVersions->method('findByEntityCodeOrdered')->willReturn([$masterVersion]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$savedVersion, &$publishedProgram, &$publishedScreen): void {
            if ($entity instanceof BuilderProgramVersion && $entity->getId() === null) {
                $this->setEntityId($entity, 701);
                $savedVersion = $entity;
            }
            if ($entity instanceof Program && $entity->getId() === null) {
                $this->setEntityId($entity, 702);
                $publishedProgram = $entity;
            }
            if ($entity instanceof ScreenDefinition && $entity->getId() === null) {
                $this->setEntityId($entity, 703);
                $publishedScreen = $entity;
            }
        });

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);

        $service = new ProgramBuilderService(
            $entities,
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $modules,
            $this->createStub(BuilderFieldRepository::class),
            $entityVersions,
            $versions,
            $programs,
            $screens,
            $this->createStub(RuntimeEndpointRepository::class),
            $entityManager,
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
            $permissions,
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );

        $draft = $service->saveDraft($this->validProgramPayload());

        self::assertSame('master_detail', $draft['pageType']);
        self::assertSame('pedido_venda', $draft['builderEntityCode']);
        self::assertSame('pedido_venda', $draft['builderConfig']['masterDetailConfig']['masterEntityCode']);
        self::assertSame('master_detail', $draft['generatedDefinition']['pageType']);

        $published = $service->publishVersion(701);

        self::assertSame('vd0101', $published['program']['code']);
        self::assertSame('vendas.pedidos', $published['program']['screenId']);
        self::assertSame('master_detail', $published['program']['programType']);
        self::assertInstanceOf(ScreenDefinition::class, $publishedScreen);
        self::assertSame('master_detail', $publishedScreen->getPageType());
        self::assertSame('vendas.pedidos', $publishedScreen->getScreenId());
        self::assertSame('master_detail', $publishedScreen->getDefinition()['pageType']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $publishedScreen->getDefinition()['runtime']['traceability']['schemaFingerprint']);

        $definitionWithoutMasterDetail = $publishedScreen->getDefinition();
        unset($definitionWithoutMasterDetail['master'], $definitionWithoutMasterDetail['details'], $definitionWithoutMasterDetail['createFlow']);
        self::assertNotSame(
            $publishedScreen->getDefinition()['runtime']['traceability']['schemaFingerprint'],
            $this->invokePrivateMixed($service, 'programSchemaFingerprint', [$savedVersion, $definitionWithoutMasterDetail])
        );
    }

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

        $invalidTotalField = $valid;
        $invalidTotalField['details'][0]['totals'] = [[
            'field' => 'total_inexistente',
            'label' => 'Total',
            'type' => 'currency',
        ]];
        yield 'campo total inexistente' => [$invalidTotalField, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];

        $textTotal = $valid;
        $textTotal['details'][0]['totals'] = [[
            'field' => 'produto',
            'label' => 'Total de produto',
            'type' => 'currency',
        ]];
        yield 'campo textual nao pode ser total currency' => [$textTotal, 'PROGRAM_BUILDER_MASTER_DETAIL_FIELD_INVALID'];

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

    private function validProgramPayload(): array
    {
        return [
            'programCode' => 'vd0101',
            'programTitle' => 'Pedido de venda',
            'module' => 'vendas',
            'pageType' => 'master_detail',
            'builderEntityCode' => 'pedido_venda',
            'screenId' => 'vendas.pedidos',
            'version' => '1.0.0',
            'permissionPrefix' => 'vendas.pedido',
            'masterDetailConfig' => $this->validMasterDetailConfig(),
        ];
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

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
