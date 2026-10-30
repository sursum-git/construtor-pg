<?php
namespace App\Tests\Runtime;
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseExpenseOptionsTest extends TestCase
{
 private function runOptions(array $body,array &$sent,bool $binding=true):array{
  $d=RuntimePurchaseExpenseAuditTest::definition();$d['fields']['despesa_id']=['optionsSource'=>['operationCode'=>'expense_options','valueField'=>'value','textField'=>'text']];$e=RuntimePurchaseExpenseAuditTest::endpoint('options');$e['bodyTemplate']['lookup']='despesa';if(!$binding)$e['bodyTemplate']['resource']='other';$d['apiSource']['operations']=[['code'=>'expense_options','type'=>'list','method'=>'POST','path'=>$e['url'],'requestContextFields'=>$e['requestContextFields'],'bodyTemplate'=>$e['bodyTemplate'],'itemsPath'=>'data','totalPath'=>'total']];
  $d['apiSource']['authHeaders']=[];
  $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback(static function($u,$m,$h,$req)use($body,&$sent){$sent[]=$req;return ['status'=>200,'body'=>$body];});
  $s=new RuntimeApiEntityActionService($this->createStub(BuilderEntityRepository::class),$this->createStub(RuntimeTransactionService::class),$client);return(new \ReflectionMethod($s,'options'))->invoke($s,$d,['fieldCode'=>'despesa_id'],['context'=>['proc_compra_id'=>10]]);
 }
 public function testOptionsKeepAllMasterDefinitionsAsInTheRegeneratedForm():void{$sent=[];self::assertSame(['data'=>[['value'=>7,'text'=>'Frete'],['value'=>9,'text'=>'Inativa na empresa']]],$this->runOptions(['success'=>true,'data'=>[['value'=>7,'text'=>'Frete'],['value'=>9,'text'=>'Inativa na empresa']],'total'=>2],$sent));self::assertSame([['resource'=>'proc_compra_despesas','operation'=>'options','lookup'=>'despesa','proc_compra_id'=>10]],$sent);}
 public function testMalformedOptionResponseCannotBePresentedAsValid():void{
  foreach(['success','total','duplicate','extra']as$case){$body=['success'=>true,'data'=>[['value'=>7,'text'=>'Frete']],'total'=>1];switch($case){case'success':$body['success']=false;break;case'total':$body['total']=2;break;case'duplicate':$body['data'][]=$body['data'][0];$body['total']=2;break;case'extra':$body['data'][0]['sql']='bad';break;}$sent=[];try{$this->runOptions($body,$sent);self::fail($case);}catch(RuntimeHttpException $e){self::assertSame(502,$e->getStatusCode());}self::assertCount(1,$sent);}
 }
 public function testOtherResourceCannotBeUsedForThisDropdown():void{$sent=[];try{$this->runOptions(['success'=>true,'data'=>[],'total'=>0],$sent,false);self::fail('binding');}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());}self::assertSame([],$sent);}
}
