<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimeEstoqueTermoLogScopeTest extends TestCase
{
 public function testTextIdentityIsAcceptedOnlyForTheStockTermScreen():void
 {
  $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();
  $filters=[['field'=>'audit_record_id','operator'=>'eq','value'=>'SC-TESTE'],['field'=>'audit_screen_id','operator'=>'eq','value'=>'iol.estoque-termos-busca']];
  $result=$r->getMethod('withAuditLogScope')->invoke($s,['entityCode'=>'runtime_transaction_log'],['filters'=>$filters],'read');
  self::assertSame('SC-TESTE',$result['_auditLogScope']['audit_record_id']);
  $filters[1]['value']='ciw.coloracoes';
  $this->expectException(\App\Runtime\RuntimeHttpException::class);
  $r->getMethod('withAuditLogScope')->invoke($s,['entityCode'=>'runtime_transaction_log'],['filters'=>$filters],'read');
 }
 public function testDeletionQueryKeepsTenantScreenAndExactTextIdentity():void
 {
  $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
  $db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');
  $db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
  foreach(range(1,5) as $i){
   $db->insert('runtime_transaction',['id'=>$i,'tenant_id'=>$i===2?'other':'a','screen_id'=>$i===3?'other':'iol.estoque-termos-busca','status'=>$i===4?'failed':'succeeded','operation'=>'delete','record_id'=>"SC-TESTE'X"]);
   $audit=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'estoque_termos','operation'=>'delete','recordId'=>$i===5?'OUTRO':"SC-TESTE'X"];
   $db->insert('runtime_transaction_log',['id'=>$i,'transaction_id'=>$i,'event_type'=>'estoque_termos_busca_progress.api.delete','metadata'=>json_encode(['audit'=>$audit])]);
  }
  $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);
  $permissions=$this->createStub(PermissionResolver::class);$permissions->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$permissions);
  $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');
  $r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'iol.estoque-termos-busca','audit_record_id'=>"SC-TESTE'X",'audit_operation'=>'delete']);
  self::assertSame([1],array_map('intval',$q->executeQuery()->fetchFirstColumn()));
 }
}
