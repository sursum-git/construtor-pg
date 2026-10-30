<?php
namespace App\Tests\Runtime;
use App\Builder\ProgramBuilderService;
use App\Entity\{BuilderEntity,BuilderField,BuilderModule};
use App\Repository\{BuilderEntityRepository,BuilderModuleRepository};
use App\Runtime\PermissionResolver;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderCiwParametrosTest extends TestCase
{
 public function testExistingBuilderRendersAssignedTextKeyAndCarriesHiddenConcurrencyState():void
 {
  $root=dirname(__DIR__,3);$file=$root.'/metadata/im-param-builder-draft.json';
  if(!is_file($file))$file=$root.'/integrations/ciw-parametros/im-param-builder-draft.json';
  $draft=json_decode(file_get_contents($file),true,flags:JSON_THROW_ON_ERROR);
  $entity=(new BuilderEntity())->setCode('im_param_progress')->setName('Parâmetros IMA')->setEntityType('api')->setMetadata(['apiSource'=>['listEndpoint'=>['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros'],'createEndpoint'=>['url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros']]]);
  foreach($draft['entityDraft']['fields'] as $f){$options=$f['options'];$options['api']=['jsonPath'=>$f['apiJsonPath'],'writePath'=>$f['apiWritePath'],'showInGrid'=>$f['apiShowInGrid'],'showInForm'=>$f['apiShowInForm'],'showInFilter'=>$f['apiShowInFilter']];
   $entity->addField((new BuilderField())->setCode($f['code'])->setLabel($f['label'])->setDataType($f['dataType'])->setPrimaryKey($f['primaryKey'])->setRequired($f['required'])->setLength($f['length']??null)->setOptions($options));
  }
  $entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
  $modules=$this->createStub(BuilderModuleRepository::class);$modules->method('findOneBy')->willReturn((new BuilderModule())->setCode('cadastros')->setName('Cadastros')->setAbbreviation('cd')->setNumberStart(1)->setNumberEnd(9999));
  $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
  $overrides=[BuilderEntityRepository::class=>$entities,BuilderModuleRepository::class=>$modules,PermissionResolver::class=>$permissions];
  $class=new \ReflectionClass(ProgramBuilderService::class);$args=[];foreach($class->getConstructor()->getParameters() as $p){$type=$p->getType()->getName();$args[]=$overrides[$type]??$this->createStub($type);}$service=$class->newInstanceArgs($args);
  $result=$service->saveDraft($draft['programDraft']);$d=$result['generatedDefinition'];
  self::assertSame('cod_param',$d['dataModel']['primaryKey']);self::assertTrue($d['dataModel']['fields']['codigo']['readonlyOnEdit']);
  self::assertSame('text',$d['dataModel']['fields']['codigo']['type']);self::assertSame('form',$d['crud']['grid']['editing']['mode']);
  self::assertNotContains('row_stamp',array_column($d['crud']['form']['fields'],'field'));
  self::assertArrayHasKey('row_stamp',$d['dataModel']['fields']);self::assertSame('dropdown',$d['dataModel']['fields']['tp_param']['editor']);
 }
}
