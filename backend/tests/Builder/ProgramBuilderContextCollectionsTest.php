<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Entity\BuilderProgramVersion;
use App\Entity\BuilderEntityVersion;
use App\Entity\RuntimeEndpoint;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderContextCollectionsTest extends TestCase {
 private function entity(): BuilderEntity { return (new BuilderEntity())->setCode('despesas')->setName('Despesas')->setEntityType('api')->setMetadata(['apiSource'=>['createEndpoint'=>['url'=>'/create'],'operations'=>[['code'=>'opcoes','type'=>'custom','path'=>'/options','itemsPath'=>'data']]]])
  ->addField((new BuilderField())->setCode('despesa_id')->setLabel('Despesa')->setPrimaryKey(true)->setDataType('integer'))
  ->addField((new BuilderField())->setCode('empresa_id')->setLabel('Empresa')->setDataType('integer')->setOptions(['editor'=>'dropdown','readonlyOnEdit'=>true,'options'=>[['value'=>0,'text'=>'Não'],['value'=>1,'text'=>'Sim']]]))
  ->addField((new BuilderField())->setCode('percentual')->setLabel('Percentual')->setDataType('decimal')->setScaleValue(4)->setOptions(['defaultValue'=>0]))
  ->addField((new BuilderField())->setCode('ids')->setLabel('IDs')->setDataType('json')->setOptions(['editor'=>'multiselect','api'=>['collection'=>['itemType'=>'integer'],'optionsSource'=>['operationCode'=>'opcoes','valueField'=>'despesa_id','textField'=>'descricao']]])); }
 private function service(array $overrides=[]): ProgramBuilderService {
  $entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($this->entity());
  $modules=$this->createStub(BuilderModuleRepository::class);$modules->method('findOneBy')->willReturn((new BuilderModule())->setCode('cadastros')->setName('Cadastros')->setAbbreviation('cd')->setNumberStart(1)->setNumberEnd(9999));
  $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
  $overrides += [BuilderEntityRepository::class=>$entities,BuilderModuleRepository::class=>$modules,PermissionResolver::class=>$permissions];
  $r=new \ReflectionClass(ProgramBuilderService::class);$args=[];foreach($r->getConstructor()->getParameters()as$p){$type=$p->getType()->getName();$args[]=$overrides[$type]??$this->createStub($type);}return $r->newInstanceArgs($args);
 }
 private function payload(): array {return ['programCode'=>'cd0092','programTitle'=>'Despesas','module'=>'cadastros','screenId'=>'ciw.despesas','builderEntityCode'=>'despesas','allowCreate'=>true,
 'rowLinks'=>[['id'=>'empresas','label'=>'Empresas','screenId'=>'ciw.empresas-despesa','parameters'=>[['field'=>'despesa_id','sourceField'=>'despesa_id']]]],
 'contextFields'=>[['field'=>'despesa_id','filter'=>true,'createDefault'=>true,'readonly'=>true,'required'=>true]]];}
 public function testDraftGeneratesTypedCollectionClosedOptionsAndNavigation(): void {
  $draft=$this->service()->saveDraft($this->payload());$d=$draft['generatedDefinition'];
  self::assertSame('array',$d['dataModel']['fields']['ids']['type']);self::assertSame('integer',$d['dataModel']['fields']['ids']['itemType']);self::assertSame('multiselect',$d['dataModel']['fields']['ids']['editor']);
  self::assertSame(['endpointId'=>'options.ids'],$d['dataModel']['fields']['ids']['optionsSource']);self::assertSame(['endpointId'=>'options.ids','method'=>'POST'],$d['dataSource']['api']['options.ids']);
  self::assertSame('dropdown',$d['dataModel']['fields']['empresa_id']['editor']);self::assertSame('integer',$d['dataModel']['fields']['empresa_id']['type']);self::assertTrue($d['dataModel']['fields']['empresa_id']['readonlyOnEdit']);
  self::assertSame(4,$d['dataModel']['fields']['percentual']['decimals']);self::assertSame(0,$d['dataModel']['fields']['percentual']['defaultValue']);
  self::assertSame('ciw.empresas-despesa',$d['crud']['grid']['rowLinks'][0]['screenId']);self::assertTrue($d['contextFields'][0]['required']);
  self::assertSame($this->payload()['rowLinks'],$draft['builderConfig']['rowLinks']);
 }
 public function testEntityNormalizationKeepsIntegerOptionsDefaultsAndCollectionContract(): void {
  $payload=['code'=>'despesas','name'=>'Despesas','entityType'=>'api','createPhysicalTable'=>false,'structureModuleCode'=>'cadastros','structureBaseNumber'=>92,
   'apiSource'=>['listEndpoint'=>['url'=>'https://progress.example/despesas','method'=>'POST'],'listResponse'=>['itemsPath'=>'data']],
   'fields'=>[
    ['code'=>'id','label'=>'ID','dataType'=>'integer','primaryKey'=>true,'apiJsonPath'=>'id'],
    ['code'=>'log_imposto','label'=>'Imposto','dataType'=>'integer','apiJsonPath'=>'log_imposto','options'=>['editor'=>'dropdown','defaultValue'=>0,'options'=>[['value'=>0,'text'=>'Não'],['value'=>1,'text'=>'Sim']]]],
    ['code'=>'calculada','label'=>'Calculada','dataType'=>'boolean','apiJsonPath'=>'calculada','options'=>['defaultValue'=>false]],
    ['code'=>'ids','label'=>'IDs','dataType'=>'json','apiJsonPath'=>'ids','options'=>['editor'=>'multiselect','defaultValue'=>[],'api'=>['collection'=>['itemType'=>'integer']]]]
   ]];
  $service=$this->service();$config=(new \ReflectionMethod($service,'normalizeEntityPayload'))->invoke($service,$payload);
  $byCode=array_column($config['fields'],null,'code');self::assertSame([['value'=>0,'text'=>'Não'],['value'=>1,'text'=>'Sim']],$byCode['log_imposto']['options']['options']);self::assertSame(0,$byCode['log_imposto']['options']['defaultValue']);self::assertFalse($byCode['calculada']['options']['defaultValue']);self::assertSame(['itemType'=>'integer'],$byCode['ids']['options']['api']['collection']);
 }
 public function testRejectsFreeUrlAndUnknownNavigationSource(): void {
  $payload=$this->payload();$payload['rowLinks'][0]['url']='javascript:evil';$this->expectException(RuntimeHttpException::class);$this->service()->saveDraft($payload);
 }
 public function testPublicationOptionsHaveReadPermissionAndFixedField(): void {
  $persisted=[];$em=$this->createMock(EntityManagerInterface::class);$em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(function($x)use(&$persisted){if($x instanceof RuntimeEndpoint)$persisted[$x->getEndpointId()]=$x;});
  $versions=$this->createStub(BuilderEntityVersionRepository::class);$versions->method('findByEntityCodeOrdered')->willReturn([new BuilderEntityVersion()]);
  $service=$this->service([EntityManagerInterface::class=>$em,BuilderEntityVersionRepository::class=>$versions]);
  $version=(new BuilderProgramVersion())->setProgramCode('cd0092')->setBuilderEntityCode('despesas')->setScreenId('ciw.despesas')->setPermissionPrefix('despesas')->setBuilderConfig([]);
  (new \ReflectionMethod($service,'upsertCrudRuntimeEndpoints'))->invoke($service,$version);
  self::assertArrayHasKey('options.ids',$persisted);self::assertSame('despesas.read',$persisted['options.ids']->getPermission());self::assertSame('options',$persisted['options.ids']->getConfig()['operation']);self::assertSame('ids',$persisted['options.ids']->getConfig()['fieldCode']);
 }

 public function testRowLinkPositionSurvivesExportAndRegeneration(): void {
  $service=$this->service();$payload=$this->payload();$payload['rowLinks'][0]['position']='start';
  $draft=$service->saveDraft($payload);
  self::assertSame('start',$draft['generatedDefinition']['crud']['grid']['rowLinks'][0]['position']);
  $export=(new \ReflectionMethod($service,'externalProgramPayload'))->invoke($service,$draft['builderConfig'],$draft['generatedDefinition']);
  self::assertSame('start',$export['rowLinks'][0]['position']);self::assertSame($draft['generatedDefinition'],$service->saveDraft($export)['generatedDefinition']);
  $payload['rowLinks'][0]['position']='end';self::assertSame('end',$service->saveDraft($payload)['builderConfig']['rowLinks'][0]['position']);
  self::assertArrayNotHasKey('position',$service->saveDraft($this->payload())['builderConfig']['rowLinks'][0]);
 }
 public function testRejectsInvalidRowLinkPosition(): void {
  foreach(['middle',null,[],false] as $position){$payload=$this->payload();$payload['rowLinks'][0]['position']=$position;$caught=false;
   try{$this->service()->saveDraft($payload);}catch(RuntimeHttpException){$caught=true;}self::assertTrue($caught);}
 }
}
