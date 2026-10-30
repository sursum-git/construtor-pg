<?php
namespace App\Tests\Runtime;
use App\Entity\{RuntimeTransaction,RuntimeTransactionLog};
use App\Repository\BuilderEntityRepository;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeExecutionContext,RuntimeEntityDefinitionResolver,RuntimeEnvironmentIdentityResolver,PermissionResolver,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseExpenseProcessTest extends TestCase
{
 private function invoke(array $body,array &$sent,array &$logs,array $payload=[],bool $allowed=true,string $actor='real',bool $binding=true):array{
  $em=$this->createStub(EntityManagerInterface::class);$em->method('persist')->willReturnCallback(static function($item)use(&$logs){if($item instanceof RuntimeTransactionLog)$logs[]=$item;});
  $p=$this->createStub(PermissionResolver::class);$p->method('hasPermission')->willReturn($allowed);
  $tx=new RuntimeTransactionService($em,$p,new RuntimeExecutionContext(),$this->createStub(RuntimeEntityDefinitionResolver::class),$this->createStub(RuntimeEnvironmentIdentityResolver::class));
  (new \ReflectionProperty($tx,'current'))->setValue($tx,(new RuntimeTransaction())->setTenantId('default')->setRequestContext(['actor'=>['source'=>'authenticated-session','version'=>1,'username'=>$actor]]));
  $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturnCallback(static function($url,$method,$headers,$request)use(&$sent,$body){$sent[]=$request;return ['status'=>200,'body'=>$body];});
  $s=new RuntimeApiEntityActionService($this->createStub(BuilderEntityRepository::class),$tx,$client,$p);$d=RuntimePurchaseExpenseAuditTest::definition();$e=RuntimePurchaseExpenseAuditTest::endpoint('generate');if(!$binding)$e['bodyTemplate']['resource']='other';
  $d['fields']['proc_compra_id']+=['code'=>'proc_compra_id','label'=>'Processo','required'=>true];$d['apiSource']['operations']=[['code'=>'proc_compra_despesas_generate','type'=>'custom','method'=>'POST','path'=>$e['url'],'allowProcess'=>true,'requestContextFields'=>$e['requestContextFields'],'bodyTemplate'=>$e['bodyTemplate'],'itemPath'=>'data']];
  return(new \ReflectionMethod($s,'process'))->invoke($s,$d,['operation'=>'process','allowProcess'=>true,'permissionPrefix'=>'op1022','contextFields'=>[['field'=>'proc_compra_id']],'processOptions'=>['operationCode'=>'proc_compra_despesas_generate','parameterFields'=>[]]],$payload+['context'=>['proc_compra_id'=>10],'parameters'=>[]]);
 }
 public function testGenerationImportsParentAndEveryCreatedExpenseWithTrustedActor():void{
  $sent=[];$logs=[];$r=$this->invoke(RuntimePurchaseExpenseAuditTest::generated(),$sent,$logs);self::assertTrue($r['audit']['available']??false);self::assertSame([['resource'=>'proc_compra_despesas','operation'=>'generate','proc_compra_id'=>10]],$sent);
  $confirmed=array_values(array_filter($logs,fn($l)=>$l->getMetadata()['audit']['available']??false));self::assertCount(2,$confirmed);self::assertSame(RuntimePurchaseExpenseAuditTest::header(),$confirmed[0]->getBeforeData());self::assertSame(RuntimePurchaseExpenseAuditTest::image(),$confirmed[1]->getAfterData());self::assertSame('real',$confirmed[1]->getMetadata()['actor']['username']);
 }
 public function testBadCommittedAuditOrPresentationWarnsWithoutAnotherPost():void{
  foreach(['actor','presentation']as$case){$b=RuntimePurchaseExpenseAuditTest::generated();if($case==='actor')$b['audit']['actor']['login']='fake';else$b['data']=['type'=>'script','script'=>'bad'];$sent=[];$logs=[];$r=$this->invoke($b,$sent,$logs);self::assertCount(1,$sent);self::assertStringContainsString('Nao repita',$r['result']['message']);if($case==='actor'){self::assertFalse($r['audit']['available']);foreach($logs as$l)self::assertSame([],$l->getBeforeData());}}
 }
 public function testPermissionParametersBindingAndIdentityFailBeforeProgress():void{
  foreach(['permission','parameter','owner','binding','identity']as$case){$sent=[];$logs=[];$payload=match($case){'parameter'=>['parameters'=>['sql'=>'bad']],'owner'=>['context'=>['proc_compra_id'=>10,'item_proc_compra_id'=>11]],default=>[]};try{$this->invoke(RuntimePurchaseExpenseAuditTest::generated(),$sent,$logs,$payload,$case!=='permission',$case==='identity'?'':'real',$case!=='binding');self::fail($case);}catch(RuntimeHttpException $e){self::assertContains($e->getStatusCode(),[401,403,422]);}self::assertSame([],$sent,$case);}
 }
 public function testMissingOrFalseCommitCannotBeReportedAsConfirmed():void{
  foreach([false,null]as$commit){$b=RuntimePurchaseExpenseAuditTest::generated();if($commit===null)unset($b['committed']);else$b['committed']=$commit;$sent=[];$logs=[];try{$this->invoke($b,$sent,$logs);self::fail('Unconfirmed process accepted');}catch(RuntimeHttpException $e){self::assertSame(502,$e->getStatusCode());}self::assertCount(1,$sent);foreach($logs as$l)self::assertFalse($l->getMetadata()['audit']['available']??false);}
 }
}
