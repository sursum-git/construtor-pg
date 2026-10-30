<?php
namespace App\Tests\Runtime;

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

final class ProgramBuilderProcessCollectionTest extends TestCase {
    private function service(bool $options=false,bool $unsafe=false,bool $list=false,bool $wrongContext=false): ProgramBuilderService
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
        $entity->addField((new BuilderField())->setCode('json_ids')->setLabel('IDs')->setDataType('json')->setRequired(true)->setOptions(['api'=>['collection'=>['itemType'=>'integer']]]));
        if($options){$m=$entity->getMetadata();$m['apiSource']['operations'][]=['code'=>'eligible','type'=>$list?'list':'custom','method'=>'POST','path'=>'https://progress.example/run','requestContextFields'=>$wrongContext?[]:['field_1'],'itemsPath'=>'data','bodyTemplate'=>['resource'=>'available','operation'=>$unsafe?'allocate':'read'],'allowProcess'=>$list?false:$unsafe];$entity->setMetadata($m);foreach($entity->getFields()as$f){if($f->getCode()==='json_ids'){$o=$f->getOptions();$o['api']['optionsSource']=['operationCode'=>'eligible','valueField'=>'id','textField'=>'label'];$f->setOptions($o);}}}
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


 private function payload(string $field):array{return ['programCode'=>'op1100','programTitle'=>'Alocar','module'=>'operacional','screenId'=>'ciw.alocar','builderEntityCode'=>'consultas','pageType'=>'process','allowCreate'=>false,'allowUpdate'=>false,'allowDelete'=>false,'processOptions'=>['operationCode'=>'sync','parameterFields'=>[$field],'confirmation'=>['message'=>'Confirma alocacao?']],'contextFields'=>[['field'=>'field_1','required'=>true,'readonly'=>true]]];}
 public function testOnlyRegisteredIntegerCollectionBecomesNativeProcessParameter():void{$d=$this->service()->saveDraft($this->payload('json_ids'))['generatedDefinition'];self::assertSame('array',$d['process']['parameters']['fields'][0]['type']);self::assertSame('integer',$d['process']['parameters']['fields'][0]['itemType']);self::assertSame('multiselect',$d['process']['parameters']['fields'][0]['editor']);}
 public function testUnrestrictedJsonStillCannotBeProcessParameter():void{$this->expectException(RuntimeHttpException::class);$this->service()->saveDraft($this->payload('json_data'));}
 public function testGeneratedOptionsAreReadEndpointWithSameContextNotRun():void{$service=$this->service(true);$draft=$service->saveDraft($this->payload('json_ids'));$d=$draft['generatedDefinition'];self::assertSame(['endpointId'=>'options.json_ids'],$d['process']['parameters']['fields'][0]['optionsSource']);self::assertSame(['endpointId'=>'options.json_ids','method'=>'POST'],$d['dataSource']['api']['options.json_ids']);$v=(new \App\Entity\BuilderProgramVersion())->setProgramCode('op1100')->setScreenId('ciw.alocar')->setPageType('process')->setBuilderEntityCode('consultas')->setPermissionPrefix('op1100')->setBuilderConfig($draft['builderConfig']);$cfg=(new \ReflectionMethod($service,'endpointConfig'))->invoke($service,$v,'options.json_ids','entity.api.crud');self::assertSame('options',$cfg['operation']);self::assertSame('json_ids',$cfg['fieldCode']);self::assertArrayNotHasKey('allowProcess',$cfg);self::assertSame('op1100.read',(new \ReflectionMethod($service,'endpointPermission'))->invoke($service,$v,'options.json_ids','entity.api.crud'));}
 public function testMislabelledListCannotWriteAndLookupMustUseFixedProcessContext():void {foreach(['write','context'] as $case){try{$this->service(true,$case==='write',true,$case==='context')->saveDraft($this->payload('json_ids'));self::fail($case);}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());}}}
 public function testProcessOptionSourceCannotPointToWritingCommand():void{$this->expectException(RuntimeHttpException::class);$this->service(true,true)->saveDraft($this->payload('json_ids'));}
}
