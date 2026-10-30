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

final class ProgramBuilderFormOptionsTest extends TestCase
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
        return ['mode' => 'embedded', 'returnScreenId' => 'ciw.consultas', 'openOnLoadField' => 'field_1', 'tabs' => [
            ['id' => 'dados', 'title' => 'Dados', 'sections' => [['id' => 'principal', 'title' => 'Principal', 'columns' => 2, 'fields' => ['field_1', 'field_2']]]],
            ['id' => 'itens', 'title' => 'Itens', 'type' => 'linkedPage', 'linkedPage' => ['screenId' => 'ciw.itens', 'engine' => 'crud', 'hideHeader' => true, 'requireRecord' => true, 'filters' => [['field' => 'container_id', 'sourceField' => 'field_1', 'operator' => 'eq']]]],
        ]];
    }

    public function testGeneratesContextualEmbeddedFormAndPreservesExport(): void
    {
        $service = $this->service();
        $payload = $this->payload() + ['formOptions' => $this->options(), 'contextFields' => [['field' => 'field_1', 'filter' => true, 'readonly' => true, 'required' => true]]];
        $draft = $service->saveDraft($payload);
        $form = $draft['generatedDefinition']['crud']['form'];
        self::assertSame('embedded', $form['mode']);
        self::assertSame('ciw.consultas', $form['returnScreenId']);
        self::assertSame('field_1', $form['openOnLoadField']);
        self::assertSame(['field' => 'field_2'], array_intersect_key($form['tabs'][0]['sections'][0]['fields'][1], ['field' => true]));
        self::assertSame('ciw.itens', $form['tabs'][1]['linkedPage']['screenId']);
        self::assertSame($this->options(), $draft['builderConfig']['formOptions']);
        $export = (new \ReflectionMethod($service, 'externalProgramPayload'))->invoke($service, $draft['builderConfig'], $draft['generatedDefinition']);
        self::assertSame($this->options(), $export['formOptions']);
        self::assertSame($draft['generatedDefinition'], $service->saveDraft($export)['generatedDefinition']);
    }

    public function testAbsentOptionsPreservePopupAndGeneralTab(): void
    {
        $form = $this->service()->saveDraft($this->payload())['generatedDefinition']['crud']['form'];
        self::assertSame('popup', $form['mode']);
        self::assertSame('geral', $form['tabs'][0]['id']);
        self::assertArrayNotHasKey('openOnLoadField', $form);
    }

    public static function invalidFormOptions(): array
    {
        return [
            'free template' => [['template' => '<script>']],
            'invalid return URL' => [['returnScreenId' => 'javascript:bad']],
            'invalid mode' => [['mode' => 'iframe']],
            'missing required context' => [['mode' => 'embedded', 'openOnLoadField' => 'field_1']],
            'non primary field' => [['openOnLoadField' => 'field_2']],
            'unknown field' => [['tabs' => [['id' => 'dados', 'title' => 'Dados', 'sections' => [['id' => 'main', 'fields' => ['missing']]]]]]],
            'unsafe child URL' => [['tabs' => [['id' => 'itens', 'title' => 'Itens', 'type' => 'linkedPage', 'linkedPage' => ['screenId' => 'ciw.itens', 'url' => 'javascript:bad']]]]],
            'unknown parent field' => [['tabs' => [['id' => 'itens', 'title' => 'Itens', 'type' => 'linkedPage', 'linkedPage' => ['screenId' => 'ciw.itens', 'filters' => [['field' => 'id', 'sourceField' => 'missing']]]]]]],
            'empty tabs' => [['tabs' => []]],
            'duplicate tab' => [['tabs' => [['id' => 'x', 'title' => 'X', 'sections' => []], ['id' => 'x', 'title' => 'X', 'sections' => []]]]],
        ];
    }

    #[DataProvider('invalidFormOptions')]
    public function testRejectsInvalidFormOptions(mixed $options): void
    {
        $this->expectException(RuntimeHttpException::class);
        $this->service()->saveDraft($this->payload() + ['formOptions' => $options]);
    }
}
