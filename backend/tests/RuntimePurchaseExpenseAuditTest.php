<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimePurchaseExpenseAudit as A,RuntimePurchaseContextAudit as C,RuntimePurchaseContextPayload as P,RuntimeCiwErpQuery};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseExpenseAuditTest extends TestCase
{
 public static function definition():array{return ['entityCode'=>'proc_compra_despesas_progress','primaryKey'=>'despesa_proc_compra_id','fields'=>['proc_compra_id'=>['dataType'=>'integer']],'apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs']];}
 public static function endpoint(string $op='update'):array{return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','requestContextFields'=>['proc_compra_id'],'bodyTemplate'=>['resource'=>'proc_compra_despesas','operation'=>$op]];}
 public static function image():array{return ['despesa_proc_compra_id'=>101,'proc_compra_id'=>10,'despesa_id'=>7,'vl_prev'=>12.5,'calc_imp_proc_compra_id'=>0];}
 public static function data():array{return self::image()+['row_stamp'=>str_repeat('A',64),'despesa_descricao'=>'Frete'];}
 public static function body(string $op='update'):array{return ['success'=>true,'committed'=>true,'data'=>$op==='delete'?['despesa_proc_compra_id'=>101]:self::data(),'audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'despesas-proc-compra','operation'=>$op,'recordId'=>101,'actor'=>['login'=>'real'],'before'=>$op==='create'?[]:self::image(),'after'=>$op==='delete'?[]:self::image(),'changes'=>[],'warnings'=>[]]];}
 public static function header():array{return ['proc_compra_id'=>10,'empresa_id'=>2,'fornecedor_id'=>7,'qt_container'=>0,'cod_situacao'=>1,'cod_proc_fornec'=>'TEST','qt_dias_translado'=>0,'cotacao_prevista'=>0,'observacao'=>'','dt_registro'=>'2026-10-23','dt_limite'=>null,'log_facilid_contato_fornec'=>true,'log_considerar_previsao'=>false,'nr_container_original'=>''];}
 public static function generated():array{$h=self::header();return ['success'=>true,'committed'=>true,'created'=>1,'skipped'=>1,'data'=>['type'=>'message','message'=>'Gerada 1 despesa ausente.'],'audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'procs-compra','operation'=>'generate','recordId'=>10,'actor'=>['login'=>'real'],'before'=>$h,'after'=>$h,'changes'=>[['resource'=>'despesas-proc-compra','operation'=>'create','recordId'=>101,'before'=>[],'after'=>self::image()]],'warnings'=>[]]];}
 public function testRegisteredCrudProfileImportsFullImagesAndReadonlyProjection():void{
  self::assertTrue(C::supports(self::definition()));self::assertTrue(C::child(self::definition()));
  foreach(['create','update','delete']as$op){$a=C::inspect(self::definition(),self::endpoint($op),self::body($op),$op,101,['proc_compra_id'=>10],'real');self::assertTrue($a['metadata']['available'],$op);self::assertSame(10,$a['metadata']['procCompraId']);self::assertSame($op==='create'?[]:self::image(),$a['before']);self::assertSame($op==='delete'?[]:self::image(),$a['after']);}
  $p=P::normalize(self::definition(),'update',self::data());self::assertSame(101,$p['id']);self::assertSame(['proc_compra_id'=>10],$p['context']);self::assertFalse(C::editableField('calc_imp_proc_compra_id',self::definition()));self::assertTrue(C::editableField('vl_prev',self::definition()));
 }
 public function testCrudRejectsForgedActorOwnerCommitAndImages():void{
  foreach(['actor','owner','commit','missing','data','id','effect','fraction','context','resource']as$case){$b=self::body();$context=['proc_compra_id'=>10];switch($case){case'actor':$b['audit']['actor']['login']='fake';break;case'owner':$b['audit']['after']['proc_compra_id']=20;break;case'commit':$b['committed']=false;break;case'missing':unset($b['audit']['before']['calc_imp_proc_compra_id']);break;case'data':$b['data']['vl_prev']=9;break;case'id':$b['audit']['recordId']=102;break;case'effect':$b['audit']['changes'][]=[];break;case'fraction':$b['audit']['after']['proc_compra_id']=10.5;break;case'context':$context['item_proc_compra_id']=11;break;case'resource':$b['audit']['resource']='anexos-proc-compra';break;}self::assertFalse(A::inspect(self::definition(),self::endpoint(),$b,'update',101,$context,'real')['metadata']['available'],$case);}
 }
 public function testReadUsesClosedResourceAndContext():void{
  self::assertSame(['skip'=>0,'take'=>10,'proc_compra_id'=>10],RuntimeCiwErpQuery::prepare(self::definition(),self::endpoint('read'),['take'=>10,'context'=>['proc_compra_id'=>10]]));
  $e=self::endpoint();$e['requestContextFields'][]='item_proc_compra_id';self::assertFalse(C::binding(self::definition(),$e,'update'));self::assertTrue(C::dataRow(self::data(),self::definition()));
 }
 public function testGenerationImportsEachCreatedRowAndUnchangedParent():void{
  $a=A::inspectGeneration(self::definition(),self::endpoint('generate'),self::generated(),['proc_compra_id'=>10],'real');self::assertTrue($a['metadata']['available']);self::assertSame('procs_compra',$a['metadata']['resource']);self::assertSame(10,$a['metadata']['recordId']);self::assertCount(1,$a['events']);self::assertSame(self::image(),$a['events'][0]['after']);self::assertSame('despesas_proc_compra',$a['events'][0]['metadata']['resource']);self::assertSame(101,$a['events'][0]['metadata']['recordId']);
 }
 public function testGenerationRejectsUnknownEffectsWrongOwnershipAndPartialCommit():void{
  foreach(['actor','owner','count','parent-change','effect-type','duplicate','missing','commit','readonly-calc']as$case){$b=self::generated();switch($case){case'actor':$b['audit']['actor']['login']='fake';break;case'owner':$b['audit']['changes'][0]['after']['proc_compra_id']=20;break;case'count':$b['created']=2;break;case'parent-change':$b['audit']['after']['observacao']='changed';break;case'effect-type':$b['audit']['changes'][0]['operation']='delete';break;case'duplicate':$b['audit']['changes'][]=$b['audit']['changes'][0];$b['created']=2;break;case'missing':unset($b['audit']['changes'][0]['after']['vl_prev']);break;case'commit':$b['committed']=false;break;case'readonly-calc':$b['audit']['changes'][0]['after']['calc_imp_proc_compra_id']=9;break;}self::assertFalse(A::inspectGeneration(self::definition(),self::endpoint('generate'),$b,['proc_compra_id'=>10],'real')['metadata']['available'],$case);}
 }
 public function testIdempotentGenerationHasNoFabricatedEffects():void{
  $b=self::generated();$b['created']=0;$b['skipped']=2;$b['audit']['changes']=[];$a=A::inspectGeneration(self::definition(),self::endpoint('generate'),$b,['proc_compra_id'=>10],'real');self::assertTrue($a['metadata']['available']);self::assertSame([],$a['events']);
 }
}
