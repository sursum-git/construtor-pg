<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseExpenseLogScopeTest extends TestCase
{
 private function query($db,string $screen,?int $record=null,?string $operation=null,bool $sql=false):mixed{
  $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);
  $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$scope=['audit_screen_id'=>$screen];if($record!==null)$scope['audit_record_id']=$record;if($operation!==null)$scope['audit_operation']=$operation;$r->getMethod('applyAuditLogScope')->invoke($s,$q,$scope);return$sql?$q->getSQL():array_map('intval',$q->executeQuery()->fetchFirstColumn());
 }
 private function db(){
  $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
  $cases=['create','update','delete','generated','summary','foreign','tenant','unconfirmed','handler','resource','typed-record','typed-owner','version','event','generation-screen','generation-delete','summary-identity','failed','fraction-owner','overflow-owner','source'];
  foreach($cases as$i=>$case){$id=$i+1;$generated=in_array($case,['generated','summary','generation-screen','generation-delete','summary-identity'],true);$summary=in_array($case,['summary','summary-identity'],true);$op=$summary?'generate_expenses':($case==='delete'||$case==='generation-delete'?'delete':($case==='update'?'update':'create'));
   $a=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$summary?'procs_compra':'despesas_proc_compra','operation'=>$op,'recordId'=>$summary?10:($generated?102:101),'procCompraId'=>10];$tx=['id'=>$id,'tenant_id'=>'a','screen_id'=>$generated?'ciw.proc-compra-gerar-despesas':'ciw.proc-compra-despesas','status'=>'succeeded','operation'=>'entity.api.crud','record_id'=>$generated?'10':'101'];$event='proc_compra_despesas_progress.api.'.($summary?'process':$op);
   switch($case){case'foreign':$a['procCompraId']=20;$a['recordId']=103;break;case'tenant':$tx['tenant_id']='b';break;case'unconfirmed':$a['available']=false;break;case'handler':$tx['operation']='other';break;case'resource':$a['resource']='other';break;case'typed-record':$a['recordId']='101';break;case'typed-owner':$a['procCompraId']='10';break;case'version':$a['version']='1';break;case'event':$event='other.api.create';break;case'generation-screen':$tx['screen_id']='other';break;case'summary-identity':$a['recordId']=11;break;case'failed':$tx['status']='failed';break;case'fraction-owner':$a['procCompraId']=10.5;break;case'overflow-owner':$a['procCompraId']=2147483648;break;case'source':$a['source']='browser';break;}
   $db->insert('runtime_transaction',$tx);$db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$a])]);
  }return$db;
 }
 public function testRecordShowsManualCrudAndGeneratedCreationOnlyForThatExpense():void{$db=$this->db();self::assertSame([1,2,3],$this->query($db,'ciw.proc-compra-despesas',101));self::assertSame([4],$this->query($db,'ciw.proc-compra-despesas',102));self::assertSame([],$this->query($db,'ciw.proc-compra-despesas',999));}
 public function testDeletionQueryDoesNotShowGenerationOrOtherWrites():void{self::assertSame([3],$this->query($this->db(),'ciw.proc-compra-despesas',null,'delete'));}
 public function testParentAggregatesExpenseEffectsAndSummaryWithoutLeakingIntoItems():void{$db=$this->db();self::assertSame([1,2,3,4,5],$this->query($db,'ciw.processos-compra-med',10));self::assertSame([],$this->query($db,'ciw.proc-compra-itens',11));self::assertSame([],$this->query($db,'ciw.processos-compra-med',null,'delete'));}
 public function testPostgresRequiresClosedWritersTypedIdentitiesAndTenant():void{$db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);$sql=$this->query($db,'ciw.proc-compra-despesas',101,sql:true);foreach(["'ciw.proc-compra-despesas'","'ciw.proc-compra-gerar-despesas'","audit_scope.operation = 'entity.api.crud'","audit_scope.status = 'succeeded'","'{audit,recordId}') = 'number'","'{audit,procCompraId}') = 'number'","2147483647","audit_scope.tenant_id = :expenseAuditTenant"]as$guard)self::assertStringContainsString($guard,$sql);}
}
