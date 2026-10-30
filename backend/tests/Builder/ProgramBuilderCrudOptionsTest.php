<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProgramBuilderCrudOptionsTest extends TestCase
{
    private function service(): ProgramBuilderService
    {
        $entity = (new BuilderEntity())->setCode('consultas')->setName('Consultas')->setEntityType('api')
            ->setMetadata(['apiSource' => ['listEndpoint' => ['url' => '/records'], 'detailEndpoint' => ['url' => '/record']]]);
        foreach (range(1, 20) as $index) {
            $entity->addField((new BuilderField())->setCode('field_' . $index)->setLabel('Campo ' . $index)
                ->setDataType('integer')->setPrimaryKey($index === 1)->setOptions([
                    'readonly' => true, 'writable' => false, 'api' => ['showInGrid' => true, 'showInFilter' => true],
                ]));
        }
        $entity->addField((new BuilderField())->setCode('json_data')->setLabel('JSON')->setDataType('json'));
        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturn((new BuilderModule())->setCode('operacional')->setName('Operacional')
            ->setAbbreviation('op')->setNumberStart(1000)->setNumberEnd(1999));
        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn($entity);
        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $overrides = [BuilderModuleRepository::class => $modules, BuilderEntityRepository::class => $entities, PermissionResolver::class => $permissions];
        $reflection = new \ReflectionClass(ProgramBuilderService::class);
        $args = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $args[] = $overrides[$type] ?? $this->createStub($type);
        }
        return $reflection->newInstanceArgs($args);
    }

    private function payload(): array
    {
        return ['programCode' => 'op1000', 'programTitle' => 'Consulta', 'module' => 'operacional', 'screenId' => 'ciw.consultas',
            'builderEntityCode' => 'consultas', 'allowCreate' => false, 'allowUpdate' => false, 'allowDelete' => false];
    }

    private function options(): array
    {
        return ['pageSize' => 30,
            'gridFields' => array_map(fn ($index) => ['field' => 'field_' . $index, 'hidden' => $index > 12, 'width' => 160], range(1, 20)),
            'filterFields' => array_map(fn ($index) => 'field_' . $index, range(1, 11)),
            'defaultSort' => [['field' => 'field_2', 'dir' => 'asc'], ['field' => 'field_3', 'dir' => 'asc'], ['field' => 'field_1', 'dir' => 'asc']],
            'defaultGroup' => [['field' => 'field_2', 'dir' => 'asc'], ['field' => 'field_3', 'dir' => 'asc']]];
    }

    public function testOptionsGenerateFullReadonlyQueryAndSurviveExportAndRegeneration(): void
    {
        $service = $this->service();
        $options = $this->options();
        $draft = $service->saveDraft($this->payload() + ['crudOptions' => $options]);
        $definition = $draft['generatedDefinition'];
        self::assertCount(20, $definition['crud']['grid']['columns']);
        self::assertCount(8, array_filter($definition['crud']['grid']['columns'], fn ($column) => $column['visible'] === false));
        self::assertSame('Campo 20', $definition['crud']['grid']['columns'][19]['title']);
        self::assertSame(160, $definition['crud']['grid']['columns'][19]['width']);
        self::assertCount(11, $definition['crud']['filter']['fields']);
        self::assertSame(30, $definition['crud']['query']['defaultPageSize']);
        self::assertSame($options['defaultSort'], $definition['crud']['query']['defaultSort']);
        self::assertSame($options['defaultGroup'], $definition['crud']['query']['defaultGroup']);
        self::assertTrue($definition['crud']['grid']['groupable']);
        self::assertFalse($definition['permissions']['create']);
        self::assertFalse($definition['permissions']['edit']);
        self::assertFalse($definition['permissions']['delete']);
        self::assertTrue($definition['dataModel']['fields']['field_20']['readonly']);
        self::assertSame($options, $draft['builderConfig']['crudOptions']);
        $export = (new \ReflectionMethod($service, 'externalProgramPayload'))->invoke($service, $draft['builderConfig'], $definition);
        self::assertSame($options, $export['crudOptions']);
        self::assertSame($definition, $service->saveDraft($export)['generatedDefinition']);
    }

    public function testLegacyDefaultsAndPartialOverridesRemainIndependent(): void
    {
        $service = $this->service();
        $legacy = $service->saveDraft($this->payload())['generatedDefinition'];
        self::assertCount(6, $legacy['crud']['grid']['columns']);
        self::assertCount(5, $legacy['crud']['filter']['fields']);
        self::assertSame(20, $legacy['crud']['query']['pageSize']);
        self::assertArrayNotHasKey('defaultGroup', $legacy['crud']['query']);
        $partial = $service->saveDraft($this->payload() + ['crudOptions' => ['pageSize' => 500]])['generatedDefinition'];
        self::assertCount(6, $partial['crud']['grid']['columns']);
        self::assertCount(5, $partial['crud']['filter']['fields']);
        self::assertSame(500, $partial['crud']['query']['defaultPageSize']);
        $empty = $service->saveDraft($this->payload() + ['crudOptions' => ['filterFields' => [], 'defaultSort' => [], 'defaultGroup' => []]])['generatedDefinition'];
        self::assertSame([], $empty['crud']['filter']['fields']);
        self::assertSame([], $empty['crud']['query']['defaultSort']);
        self::assertSame([], $empty['crud']['query']['defaultGroup']);
    }

    public static function invalidOptions(): array
    {
        return [
            'unknown root key' => [['template' => 'free code']],
            'page below minimum' => [['pageSize' => 0]], 'page above maximum' => [['pageSize' => 501]],
            'page string' => [['pageSize' => '30']], 'not object' => [true],
            'grid missing field' => [['gridFields' => [['field' => 'missing']]]],
            'grid JSON' => [['gridFields' => [['field' => 'json_data']]]],
            'grid duplicate' => [['gridFields' => [['field' => 'field_1'], ['field' => 'field_1']]]],
            'grid empty' => [['gridFields' => []]],
            'grid code' => [['gridFields' => [['field' => 'field_1', 'template' => 'javascript:code']]]],
            'hidden not boolean' => [['gridFields' => [['field' => 'field_1', 'hidden' => 'true']]]],
            'width not integer' => [['gridFields' => [['field' => 'field_1', 'width' => '150px']]]],
            'width outside range' => [['gridFields' => [['field' => 'field_1', 'width' => 1201]]]],
            'filter duplicate' => [['filterFields' => ['field_1', 'field_1']]],
            'filter JSON' => [['filterFields' => ['json_data']]],
            'filter object' => [['filterFields' => [['field' => 'field_1']]]],
            'sort unknown' => [['defaultSort' => [['field' => 'missing', 'dir' => 'asc']]]],
            'sort duplicate' => [['defaultSort' => [['field' => 'field_1', 'dir' => 'asc'], ['field' => 'field_1', 'dir' => 'desc']]]],
            'sort direction' => [['defaultSort' => [['field' => 'field_1', 'dir' => 'evil']]]],
            'sort extra key' => [['defaultSort' => [['field' => 'field_1', 'dir' => 'asc', 'compare' => 'free']]]],
            'group JSON' => [['defaultGroup' => [['field' => 'json_data', 'dir' => 'asc']]]],
            'too many groups' => [['defaultGroup' => array_map(fn ($i) => ['field' => 'field_' . $i, 'dir' => 'asc'], range(1, 4))]],
        ];
    }

    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOptionsBeforeSaving(mixed $options): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->service()->saveDraft($this->payload() + ['crudOptions' => $options]);
    }
}
