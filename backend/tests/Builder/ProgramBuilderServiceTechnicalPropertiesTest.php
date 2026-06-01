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
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramBuilderServiceTechnicalPropertiesTest extends TestCase
{
    public function testProgramFieldTechnicalPropertiesIncludeApiAndOdooMetadata(): void
    {
        $service = $this->service();
        $entity = (new BuilderEntity())
            ->setCode('parceiros_odoo')
            ->setName('Parceiros Odoo')
            ->setEntityType('api')
            ->setMetadata([
                'apiSourceCode' => 'odoo_mock',
                'apiListOperationCode' => 'odoo_list',
                'apiDetailOperationCode' => 'odoo_detail',
                'apiSource' => [
                    'providerType' => 'odoo',
                    'odoo' => [
                        'model' => 'res.partner',
                        'transport' => 'jsonrpc',
                    ],
                ],
            ]);

        $field = (new BuilderField())
            ->setCode('nome')
            ->setLabel('Nome')
            ->setDataType('string')
            ->setRequired(true)
            ->setPosition(1)
            ->setOptions([
                'readonly' => true,
                'api' => [
                    'jsonPath' => 'name',
                    'lookupResolver' => [
                        'operationCode' => 'parceiro_lookup',
                        'sourceField' => 'partner_id',
                        'mode' => 'batch',
                    ],
                ],
            ]);

        $properties = $this->invokePrivate(
            $service,
            'programFieldTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );

        self::assertSame('name', $this->findPropertyValue($properties, 'JSON Path'));
        self::assertTrue($this->findPropertyCritical($properties, 'JSON Path'));
        self::assertSame('parceiro_lookup', $this->findPropertyValue($properties, 'Lookup operacao'));
        self::assertSame('partner_id', $this->findPropertyValue($properties, 'Lookup origem'));
        self::assertSame('batch', $this->findPropertyValue($properties, 'Lookup modo'));
        self::assertSame('odoo_mock', $this->findPropertyValue($properties, 'API cadastrada'));
        self::assertSame('odoo_list', $this->findPropertyValue($properties, 'Operacao de lista'));
        self::assertSame('odoo_detail', $this->findPropertyValue($properties, 'Operacao de detalhe'));
        self::assertSame('res.partner', $this->findPropertyValue($properties, 'Modelo'));
        self::assertSame('JSONRPC', $this->findPropertyValue($properties, 'Transporte'));
        self::assertSame('Sim', $this->findPropertyValue($properties, 'Somente leitura'));
        self::assertTrue($this->findPropertyCritical($properties, 'Somente leitura'));
    }

    public function testGridAndFilterTechnicalPropertiesAddSurfaceMetadata(): void
    {
        $service = $this->service();
        $entity = (new BuilderEntity())
            ->setCode('cliente')
            ->setName('Cliente')
            ->setEntityType('persistence')
            ->setTableName('t_cliente');

        $field = (new BuilderField())
            ->setCode('nome')
            ->setLabel('Nome')
            ->setDataType('string')
            ->setDatabaseType('varchar')
            ->setLength(120)
            ->setPosition(1)
            ->setOptions([
                'columnName' => 'nome',
            ]);

        $gridProperties = $this->invokePrivate(
            $service,
            'programGridTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );
        $filterProperties = $this->invokePrivate(
            $service,
            'programFilterTechnicalProperties',
            [$entity, $field, 'string', $field->getOptions()]
        );

        self::assertSame('Grid', $this->findPropertyValue($gridProperties, 'Superficie'));
        self::assertSame('150', $this->findPropertyValue($gridProperties, 'Largura sugerida'));
        self::assertSame('Filtro', $this->findPropertyValue($filterProperties, 'Superficie'));
        self::assertSame('contains', $this->findPropertyValue($filterProperties, 'Operador inicial'));
        self::assertSame('t_cliente', $this->findPropertyValue($gridProperties, 'Tabela'));
        self::assertSame('nome', $this->findPropertyValue($filterProperties, 'Coluna'));
    }

    public function testParseDatabaseDdlBuildsTableMetadataWithoutExecutingSql(): void
    {
        $service = $this->service();
        $table = $this->invokePrivate($service, 'parseDatabaseDdl', [
            <<<'SQL'
CREATE TABLE public.produto (
  id SERIAL PRIMARY KEY,
  c_nome VARCHAR(120) NOT NULL,
  d_vl NUMERIC(14, 2) DEFAULT 0,
  categoria_id INTEGER REFERENCES categoria(id) ON DELETE RESTRICT,
  CONSTRAINT uk_produto_nome UNIQUE (c_nome)
);
COMMENT ON TABLE public.produto IS 'Produto';
COMMENT ON COLUMN public.produto.c_nome IS 'Nome do produto';
SQL
        ]);

        self::assertSame('public', $table['schema']);
        self::assertSame('produto', $table['tableName']);
        self::assertSame('Produto', $table['tableComment']);
        self::assertSame(['id'], $table['primaryKeys']);
        self::assertSame('c_nome', $table['uniqueConstraints'][0]['columns'][0]);
        self::assertSame('categoria', $table['foreignKeys']['categoria_id']['table']);
        self::assertSame('restrict', $table['foreignKeys']['categoria_id']['onDelete']);
        self::assertSame('Nome do produto', $table['columns'][1]['column_comment']);
        self::assertSame('numeric', $table['columns'][2]['data_type']);
        self::assertSame(14, $table['columns'][2]['numeric_precision']);
        self::assertSame(2, $table['columns'][2]['numeric_scale']);
    }

    public function testParseDatabaseDdlRejectsUnsafeStatements(): void
    {
        $service = $this->service();

        $this->expectException(\App\Runtime\RuntimeHttpException::class);
        $this->invokePrivate($service, 'parseDatabaseDdl', [
            'CREATE TABLE public.produto (id SERIAL PRIMARY KEY); DROP TABLE auth_user;',
        ]);
    }

    public function testAnalyticsDefinitionHonorsWizardBlueprintAndChainedJoinSource(): void
    {
        $pedido = $this->entity('pedido', 'Pedido', 't_pedido', [
            $this->field('id', 'Id', 'integer', 1, ['primaryKey' => true]),
            $this->field('cliente_id', 'Cliente', 'integer', 2),
            $this->field('empresa_id', 'Empresa', 'integer', 3),
            $this->field('data_prevista', 'Data prevista', 'date', 4),
        ]);
        $pedidoItem = $this->entity('pedido_item', 'Item do pedido', 't_pedido_item', [
            $this->field('id', 'Id', 'integer', 1, ['primaryKey' => true]),
            $this->field('pedido_id', 'Pedido', 'integer', 2),
            $this->field('produto_id', 'Produto', 'integer', 3),
            $this->field('valor_previsto', 'Valor previsto', 'decimal', 4, ['analytics' => ['measure' => true, 'format' => 'c2']]),
        ]);
        $cliente = $this->entity('cliente', 'Cliente', 't_cliente', [
            $this->field('id', 'Id', 'integer', 1, ['primaryKey' => true]),
            $this->field('nome', 'Cliente', 'string', 2, ['analytics' => ['dimension' => true]]),
        ]);
        $empresa = $this->entity('empresa', 'Empresa', 't_empresa', [
            $this->field('id', 'Id', 'integer', 1, ['primaryKey' => true]),
            $this->field('nome_fantasia', 'Empresa', 'string', 2, ['analytics' => ['dimension' => true]]),
        ]);
        $produto = $this->entity('produto', 'Produto', 't_produto', [
            $this->field('id', 'Id', 'integer', 1, ['primaryKey' => true]),
            $this->field('descricao', 'Produto', 'string', 2, ['analytics' => ['dimension' => true]]),
        ]);

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($pedido, $pedidoItem, $cliente, $empresa, $produto): ?BuilderEntity {
                return match ($criteria['code'] ?? null) {
                    'pedido' => $pedido,
                    'pedido_item' => $pedidoItem,
                    'cliente' => $cliente,
                    'empresa' => $empresa,
                    'produto' => $produto,
                    default => null,
                };
            });

        $service = $this->service($entities);

        $normalized = $this->invokePrivateMixed($service, 'normalizeAnalyticsBuilderConfig', [[
            'joins' => [
                ['id' => 'pedido', 'source' => 'base', 'localField' => 'pedido_id', 'entityCode' => 'pedido', 'foreignField' => 'id', 'type' => 'left'],
                ['id' => 'cliente', 'source' => 'pedido', 'localField' => 'cliente_id', 'entityCode' => 'cliente', 'foreignField' => 'id', 'type' => 'left'],
                ['id' => 'empresa', 'source' => 'pedido', 'localField' => 'empresa_id', 'entityCode' => 'empresa', 'foreignField' => 'id', 'type' => 'left'],
                ['id' => 'produto', 'source' => 'base', 'localField' => 'produto_id', 'entityCode' => 'produto', 'foreignField' => 'id', 'type' => 'left'],
            ],
            'datasetBlueprint' => [
                'dimensions' => [
                    ['id' => 'cliente_nome', 'source' => 'cliente', 'field' => 'cliente.nome', 'label' => 'Cliente', 'type' => 'string'],
                    ['id' => 'empresa_nome', 'source' => 'empresa', 'field' => 'empresa.nome_fantasia', 'label' => 'Empresa', 'type' => 'string'],
                    ['id' => 'produto_descricao', 'source' => 'produto', 'field' => 'produto.descricao', 'label' => 'Produto', 'type' => 'string'],
                    ['id' => 'data_prevista', 'source' => 'pedido', 'field' => 'pedido.data_prevista', 'label' => 'Data prevista', 'type' => 'date'],
                ],
                'measures' => [
                    ['id' => 'valor_previsto_sum', 'source' => 'base', 'field' => 'valor_previsto', 'label' => 'Valor previsto', 'type' => 'decimal', 'aggregate' => 'sum', 'format' => 'c2'],
                ],
                'fields' => [
                    ['id' => 'cliente_nome', 'source' => 'cliente', 'field' => 'cliente.nome', 'label' => 'Cliente', 'type' => 'string'],
                    ['id' => 'empresa_nome', 'source' => 'empresa', 'field' => 'empresa.nome_fantasia', 'label' => 'Empresa', 'type' => 'string'],
                    ['id' => 'produto_descricao', 'source' => 'produto', 'field' => 'produto.descricao', 'label' => 'Produto', 'type' => 'string'],
                    ['id' => 'data_prevista', 'source' => 'pedido', 'field' => 'pedido.data_prevista', 'label' => 'Data prevista', 'type' => 'date'],
                    ['id' => 'valor_previsto_sum', 'source' => 'base', 'field' => 'valor_previsto', 'label' => 'Valor previsto', 'type' => 'decimal', 'aggregate' => 'sum', 'format' => 'c2'],
                ],
                'parameters' => [
                    ['id' => 'cliente_nome', 'source' => 'cliente', 'field' => 'cliente.nome', 'label' => 'Cliente', 'type' => 'text', 'operator' => 'contains'],
                ],
                'defaultSort' => [
                    ['field' => 'data_prevista', 'dir' => 'asc'],
                ],
                'chartCategoryField' => 'cliente_nome',
                'chartValueField' => 'valor_previsto_sum',
            ],
            'defaultSortField' => 'data_prevista',
            'chartCategoryField' => 'cliente_nome',
            'chartValueField' => 'valor_previsto_sum',
        ], $pedidoItem]);

        $definition = $this->invokePrivateMixed($service, 'generateAnalyticsDefinition', [[
            '_entity' => $pedidoItem,
            'programCode' => 'bi1001',
            'programTitle' => 'Vendas previstas',
            'screenId' => 'analytics.pedidos',
            'pageType' => 'analytics',
            'permissionPrefix' => 'bi1001',
            'analyticsConfig' => $normalized,
        ]]);

        self::assertCount(4, $normalized['joins']);
        self::assertSame('pedido', $normalized['joins'][1]['source']);
        self::assertSame('cliente.nome', $normalized['datasetBlueprint']['dimensions'][0]['field']);
        self::assertSame('valor_previsto_sum', $normalized['datasetBlueprint']['measures'][0]['id']);
        self::assertSame('cliente_nome', $normalized['datasetBlueprint']['chartCategoryField']);
        self::assertSame('valor_previsto_sum', $normalized['datasetBlueprint']['chartValueField']);
        self::assertSame('cliente_nome', $definition['analytics']['datasets'][0]['dimensions'][0]['id']);
        self::assertSame('produto.descricao', $definition['analytics']['datasets'][0]['dimensions'][2]['field']);
        self::assertSame('sum', $definition['analytics']['datasets'][0]['measures'][0]['aggregate']);
        self::assertSame('data_prevista', $definition['analytics']['datasets'][0]['defaultSort'][0]['field']);
    }

    private function service(?BuilderEntityRepository $entities = null): ProgramBuilderService
    {
        return new ProgramBuilderService(
            $entities ?? $this->createStub(BuilderEntityRepository::class),
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

    /**
     * @param array<int, mixed> $arguments
     * @return array<int, array<string, mixed>>
     */
    private function invokePrivate(object $target, string $method, array $arguments): array
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        /** @var array<int, array<string, mixed>> $result */
        $result = $reflection->invokeArgs($target, $arguments);

        return $result;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMixed(object $target, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }

    private function entity(string $code, string $name, string $tableName, array $fields): BuilderEntity
    {
        $entity = (new BuilderEntity())
            ->setCode($code)
            ->setName($name)
            ->setEntityType('persistence')
            ->setTableName($tableName);
        foreach ($fields as $field) {
            $entity->addField($field);
        }

        return $entity;
    }

    private function field(string $code, string $label, string $type, int $position, array $options = []): BuilderField
    {
        return (new BuilderField())
            ->setCode($code)
            ->setLabel($label)
            ->setDataType($type)
            ->setPosition($position)
            ->setOptions($options);
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    private function findPropertyValue(array $properties, string $label): ?string
    {
        foreach ($properties as $property) {
            if (($property['label'] ?? null) === $label) {
                return isset($property['value']) ? (string) $property['value'] : null;
            }
        }

        self::fail(sprintf('Property "%s" not found.', $label));
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    private function findPropertyCritical(array $properties, string $label): bool
    {
        foreach ($properties as $property) {
            if (($property['label'] ?? null) === $label) {
                return ($property['critical'] ?? false) === true;
            }
        }

        self::fail(sprintf('Property "%s" not found.', $label));
    }
}
