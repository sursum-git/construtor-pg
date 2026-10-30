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

final class ProgramBuilderProcessOptionsTest extends TestCase
{
    private function service(): ProgramBuilderService
    {
        $entity = (new BuilderEntity())->setCode('consultas')->setName('Consultas')->setEntityType('api')
            ->setMetadata(['apiSource' => ['responseContract'=>'sursum-programs','listEndpoint' => ['url' => '/records'], 'detailEndpoint' => ['url' => '/record'], 'operations' => [['code'=>'sync','allowProcess'=>true,'type'=>'custom','method'=>'POST','path'=>'https://progress.example/run','requestContextFields'=>['field_1'],'itemPath'=>'data','bodyTemplate'=>['operation'=>'sincronizar']]]]]);
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
        $versions=$this->createStub(\App\Repository\BuilderEntityVersionRepository::class);$versions->method('findByEntityCodeOrdered')->willReturn([new \App\Entity\BuilderEntityVersion()]);
        $overrides = [\App\Repository\BuilderEntityVersionRepository::class=>$versions, BuilderModuleRepository::class => $modules, BuilderEntityRepository::class => $entities, PermissionResolver::class => $permissions];
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



    private function processPayload(): array {
        return array_replace($this->payload(),['pageType'=>'process','processOptions'=>['operationCode'=>'sync','parameterFields'=>['field_2'],'confirmation'=>['message'=>'Atualiza os impostos e as despesas deste registro.']],
            'contextFields'=>[['field'=>'field_1','required'=>true,'readonly'=>true]]]);
    }
    public function testBuildsProcessWithoutExecutingAndPreservesExport(): void {
        $service=$this->service();$draft=$service->saveDraft($this->processPayload());$d=$draft['generatedDefinition'];
        self::assertSame('process',$d['pageType']);self::assertSame(['endpointId'=>'run','method'=>'POST'],$d['dataSource']['api']['process']);
        self::assertSame('op1000.process', $d['permissions']['process']);
        self::assertSame(['field_2','field_1'],array_column($d['process']['parameters']['fields'],'field'));
        self::assertTrue($d['process']['parameters']['fields'][1]['readonly']);self::assertSame('none',$d['process']['wait']['mode']);
        self::assertSame('Atualiza os impostos e as despesas deste registro.',$d['process']['actions']['process']['confirmation']['message']);
        $export=(new \ReflectionMethod($service,'externalProgramPayload'))->invoke($service,$draft['builderConfig'],$d);
        self::assertSame($this->processPayload()['processOptions'],$export['processOptions']);self::assertSame($d,$service->saveDraft($export)['generatedDefinition']);
    }
    public function testPublicationCreatesOnlyAuthorizedRunEndpoint(): void {
        $service=$this->service();$draft=$service->saveDraft($this->processPayload());
        $version=(new \App\Entity\BuilderProgramVersion())->setProgramCode('op1000')->setScreenId('ciw.process')->setPageType('process')->setBuilderEntityCode('consultas')->setPermissionPrefix('op1000')->setBuilderConfig($draft['builderConfig'])->setGeneratedDefinition($draft['generatedDefinition']);
        $config=(new \ReflectionMethod($service,'endpointConfig'))->invoke($service,$version,'run','entity.api.crud');
        self::assertSame('process',$config['operation']);self::assertSame($draft['builderConfig']['processOptions'],$config['processOptions']);
        self::assertSame('op1000.process',(new \ReflectionMethod($service,'endpointPermission'))->invoke($service,$version,'run','entity.api.crud'));
    }
    public static function invalidProcessOptions(): array {
        return [[['operationCode'=>'missing','parameterFields'=>[],'confirmation'=>['message'=>'Confirme.']]],
            [['operationCode'=>'sync','parameterFields'=>['missing'],'confirmation'=>['message'=>'Confirme.']]],
            [['operationCode'=>'sync','parameterFields'=>['field_2'],'url'=>'https://evil.example','confirmation'=>['message'=>'Confirme.']]],
            [['operationCode'=>'sync','parameterFields'=>['field_2']]],
            [['operationCode'=>'sync','parameterFields'=>['field_2'],'confirmation'=>['message'=>'','script'=>'bad']]]];
    }
    #[DataProvider('invalidProcessOptions')]
    public function testRejectsOpenOrMissingProcessContract(array $options): void {
        $payload=$this->processPayload();$payload['processOptions']=$options;$this->expectException(RuntimeHttpException::class);$this->service()->saveDraft($payload);
    }
}
