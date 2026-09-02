<?php

declare(strict_types=1);

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
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
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\ProgramOverlayService;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class ProgramBuilderMasterDetailFixtureHarness extends TestCase
{
    public function stub(string $type): object
    {
        return $this->createStub($type);
    }
}

function fixtureField(string $code, string $label, string $type, int $position, bool $primaryKey = false, bool $required = false): BuilderField
{
    return (new BuilderField())
        ->setCode($code)
        ->setLabel($label)
        ->setDataType($type)
        ->setPosition($position)
        ->setPrimaryKey($primaryKey)
        ->setRequired($required);
}

function fixtureEntity(string $code, string $name, array $fields): BuilderEntity
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

$harness = new ProgramBuilderMasterDetailFixtureHarness('fixture');
$master = fixtureEntity('pedido_venda', 'Pedido de venda', [
    fixtureField('id', 'ID', 'integer', 1, true),
    fixtureField('numero', 'Numero', 'string', 2, false, true),
    fixtureField('cliente', 'Cliente', 'string', 3, false, true),
]);
$items = fixtureEntity('pedido_item', 'Item do pedido', [
    fixtureField('id', 'ID', 'integer', 1, true),
    fixtureField('pedido_id', 'Pedido', 'integer', 2),
    fixtureField('produto', 'Produto', 'string', 3),
    fixtureField('quantidade', 'Quantidade', 'decimal', 4),
    fixtureField('valor_total', 'Valor total', 'currency', 5),
]);
$installments = fixtureEntity('pedido_parcela', 'Parcela do pedido', [
    fixtureField('id', 'ID', 'integer', 1, true),
    fixtureField('pedido_id', 'Pedido', 'integer', 2),
    fixtureField('numero', 'Numero', 'integer', 3),
    fixtureField('vencimento', 'Vencimento', 'date', 4),
    fixtureField('valor', 'Valor', 'currency', 5),
]);
$module = (new BuilderModule())
    ->setCode('vendas')
    ->setName('Vendas')
    ->setAbbreviation('vd')
    ->setNumberStart(100)
    ->setNumberEnd(199);

$entities = $harness->stub(BuilderEntityRepository::class);
$entities->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($master, $items, $installments): ?BuilderEntity {
    return match ($criteria['code'] ?? null) {
        'pedido_venda' => $master,
        'pedido_item' => $items,
        'pedido_parcela' => $installments,
        default => null,
    };
});
$modules = $harness->stub(BuilderModuleRepository::class);
$modules->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'vendas'] ? $module : null);
$permissions = $harness->stub(PermissionResolver::class);
$permissions->method('hasPermission')->willReturn(true);

$service = new ProgramBuilderService(
    $entities,
    $harness->stub(BuilderApiSourceRepository::class),
    $harness->stub(BuilderEditorLockRepository::class),
    $modules,
    $harness->stub(BuilderFieldRepository::class),
    $harness->stub(BuilderEntityVersionRepository::class),
    $harness->stub(BuilderProgramVersionRepository::class),
    $harness->stub(ProgramRepository::class),
    $harness->stub(ScreenDefinitionRepository::class),
    $harness->stub(RuntimeEndpointRepository::class),
    $harness->stub(EntityManagerInterface::class),
    $harness->stub(StructuralIntegrityService::class),
    $harness->stub(ProgramGovernanceService::class),
    $harness->stub(ProgramOverlayService::class),
    $harness->stub(RuntimeNotificationService::class),
    $harness->stub(RuntimeEnvironmentIdentityResolver::class),
    $permissions,
    $harness->stub(RuntimeSessionGuard::class),
    $harness->stub(OdooClient::class),
    $harness->stub(RuntimeEventService::class),
);

$preview = $service->previewDraft([
    'programCode' => 'vd0101',
    'programTitle' => 'Pedido de venda',
    'module' => 'vendas',
    'pageType' => 'master_detail',
    'builderEntityCode' => 'pedido_venda',
    'screenId' => 'vendas.pedidos-builder-smoke',
    'version' => '1.0.0',
    'permissionPrefix' => 'vendas.pedido',
    'masterDetailConfig' => [
        'masterEntityCode' => 'pedido_venda',
        'createFlow' => [
            'mode' => 'draftWithChildren',
            'endpointId' => 'createGraph',
        ],
        'details' => [
            [
                'entityCode' => 'pedido_item',
                'title' => 'Itens',
                'parentField' => 'pedido_id',
                'displayFields' => ['produto', 'quantidade', 'valor_total'],
                'totals' => [['field' => 'valor_total', 'label' => 'Total dos itens', 'type' => 'currency']],
            ],
            [
                'entityCode' => 'pedido_parcela',
                'title' => 'Parcelas',
                'parentField' => 'pedido_id',
                'displayFields' => ['numero', 'vencimento', 'valor'],
                'totals' => [['field' => 'valor', 'label' => 'Total das parcelas', 'type' => 'currency']],
            ],
        ],
    ],
]);

echo json_encode($preview['generatedDefinition'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
