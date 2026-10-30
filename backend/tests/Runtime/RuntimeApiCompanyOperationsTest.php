<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,BuilderField,RuntimeUserSession};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{ApiCompanyContext,ApiCompanyContextResolver,ApiCompanyRequestAdapter,RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,PermissionResolver,RuntimeHttpException};
use App\Auth\AuthenticatedSessionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeApiCompanyOperationsTest extends TestCase
{
 private function service(callable $http,?callable $resolve=null):RuntimeApiEntityActionService {
  $operations=[];$api=['authHeaders'=>[],'responseContract'=>'sursum-programs','listResponse'=>['itemsPath'=>'data'],'detailResponse'=>['itemPath'=>'data'],'createResponse'=>['itemPath'=>'data'],'updateResponse'=>['itemPath'=>'data'],'batchResponse'=>['itemsPath'=>'data']];
  foreach(['read'=>'listEndpoint','get'=>'detailEndpoint','create'=>'createEndpoint','update'=>'updateEndpoint','delete'=>'deleteEndpoint','batch'=>'batchEndpoint']as $op=>$key){$api[$key]=['url'=>'/fixture/'.$op,'method'=>'POST','bodyTemplate'=>['operation'=>$op]];$operations[$op]=['mode'=>'company','adapter'=>'company'];}
  $api['operations']=[['code'=>'lookup','type'=>'custom','method'=>'POST','path'=>'/fixture/options','itemsPath'=>'data'],['code'=>'run','type'=>'custom','method'=>'POST','path'=>'/fixture/process','itemPath'=>'data','allowProcess'=>true]];
  foreach(['options','lookup','process','run']as $op)$operations[$op]=['mode'=>'company','adapter'=>'company'];
  $entity=(new BuilderEntity())->setCode('a')->setEntityType('api')->setMetadata(['apiCompanyContext'=>['version'=>1,'moduleId'=>'ciw','operations'=>$operations],'apiSource'=>$api]);
  $entity->addField((new BuilderField())->setCode('id')->setDataType('integer')->setPrimaryKey(true)->setOptions(['readonly'=>true]));
  $entity->addField((new BuilderField())->setCode('nome')->setDataType('string')->setRequired(true));
  $entity->addField((new BuilderField())->setCode('relation_id')->setDataType('integer')->setOptions(['api'=>['optionsSource'=>['operationCode'=>'lookup','valueField'=>'id','textField'=>'nome']]]));
  $repo=$this->createStub(BuilderEntityRepository::class);$repo->method('findOneBy')->willReturn($entity);
  $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback($http);
  $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
  $sessions=$this->createStub(AuthenticatedSessionResolver::class);$sessions->method('resolve')->willReturn((new RuntimeUserSession())->setUserId('test-only'));
  $contexts=$this->createStub(ApiCompanyContextResolver::class);
  $context=new ApiCompanyContext('ciw','fixture','company',2,['key'=>'a','companyCode'=>'a','externalCompanyId'=>5,'ems2Code'=>5,'ems5Code'=>500,'revision'=>1],['body'=>['api_empresa_id'=>'externalCompanyId']],['company'=>['body'=>['api_empresa_id'=>'externalCompanyId']]]);
  $contexts->method('forOperation')->willReturnCallback($resolve??fn()=>$context);
  return new RuntimeApiEntityActionService($repo,$this->createStub(RuntimeTransactionService::class),$client,$permissions,$sessions,$contexts,new ApiCompanyRequestAdapter(),$this->createStub(\App\Runtime\ApiCompanyAuditClassifier::class));
 }
 public static function calls():array{return array_map(fn($op)=>[$op],['read','get','create','update','delete','batch','options','process']);}
 #[DataProvider('calls')]
 public function testEveryOperationUsesTheSameServerCompanyAtHttpBoundary(string $operation):void {
  $calls=[];$service=$this->service(function($url,$method,$headers,$body)use(&$calls,$operation){$calls[]=$body;self::assertSame(5,$body['api_empresa_id']);self::assertSame('POST',$method);
   $data=match($operation){'read','batch','options'=>[['id'=>7,'nome'=>'Fixture']], 'process'=>['type'=>'message','message'=>'Fixture'],default=>['id'=>7,'nome'=>'Fixture']};
   return ['status'=>200,'body'=>['success'=>true,'data'=>$data]];
  });
  $config=['entityCode'=>'a','operation'=>$operation,'permissionPrefix'=>'a','allowCreate'=>true,'allowUpdate'=>true,'allowDelete'=>true,'allowProcess'=>true,'fieldCode'=>'relation_id','processOptions'=>['operationCode'=>'run','parameterFields'=>['nome']]];
  $input=match($operation){'get','delete'=>['id'=>7],'create'=>['record'=>['nome'=>'Fixture']],'update'=>['record'=>['id'=>7,'nome'=>'Fixture']],'batch'=>['changes'=>[['clientId'=>'fixture-1','operation'=>'create','data'=>['nome'=>'Fixture']]]],'process'=>['parameters'=>['nome'=>'Fixture']],default=>[]};
  $result=$service->handle('ciw.a',$operation,$config,$input+['apiCompany'=>['selectionRevision'=>2]]);
  self::assertNotEmpty($result);self::assertCount(1,$calls);
 }
 public function testStaleContextNeverReachesExternalHttp():void {
  $service=$this->service(function(){self::fail('External request after rejection');},function(){throw new RuntimeHttpException('API_COMPANY_CONTEXT_CHANGED','Changed',409);});
  $this->expectException(RuntimeHttpException::class);$service->handle('ciw.a','read',['entityCode'=>'a','operation'=>'read'],['apiCompany'=>['selectionRevision'=>1]]);
 }
 public function testLateRevocationDoesNotExposeReadData():void {
  $count=0;$service=$this->service(fn()=>['status'=>200,'body'=>['data'=>[['id'=>7]]]],function()use(&$count){if(++$count===2)throw new RuntimeHttpException('API_COMPANY_FORBIDDEN','Revoked',403);return new ApiCompanyContext('ciw','fixture','company',2,['externalCompanyId'=>5,'ems2Code'=>5,'ems5Code'=>500],['body'=>['api_empresa_id'=>'externalCompanyId']],['company'=>['body'=>['api_empresa_id'=>'externalCompanyId']]]);});
  $this->expectException(RuntimeHttpException::class);$this->expectExceptionMessage('Revoked');$service->handle('ciw.a','read',['entityCode'=>'a','operation'=>'read'],['apiCompany'=>['selectionRevision'=>2]]);
 }
}
