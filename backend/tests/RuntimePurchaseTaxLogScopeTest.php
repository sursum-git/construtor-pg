<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseTaxLogScopeTest extends TestCase
{
 private function query($db,string $screen,?int $record=null,?string $operation=null,bool $sql=false):mixed{
  $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);
  $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$scope=['audit_screen_id'=>$screen];if($record!==null)$scope['audit_record_id']=$record;if($operation!==null)$scope['audit_operation']=$operation;$r->getMethod('applyAuditLogScope')->invoke($s,$q,$scope);return$sql?$q->getSQL():array_map('intval',$q->executeQuery()->fetchFirstColumn());
 }

 private function db(){
  $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
  $cases=['manual','root','header','update','create','foreign','tenant','unconfirmed','handler','screen','event','typed-id','typed-owner','typed-calculation','zero-calculation','overflow-calculation','header-identity','root-identity','tax-delete','failed','origin','resource','root-operation','header-operation'];
  foreach($cases as$i=>$case){$id=$i+1;$root=in_array($case,['root','root-identity','root-operation'],true);$header=in_array($case,['header','header-identity','header-operation'],true);$op=$root?'calculate_taxes':($header||$case==='create'?'create':'update');
   $a=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$root?'procs_compra':($header?'calcs_imp_proc_compra':'despesas_proc_compra'),'operation'=>$op,'recordId'=>$root?10:($header?501:($case==='create'?102:101)),'procCompraId'=>10,'calculationId'=>501];$tx=['id'=>$id,'tenant_id'=>'a','screen_id'=>$case==='manual'?'ciw.proc-compra-despesas':'ciw.proc-compra-calcular-impostos','status'=>'succeeded','operation'=>'entity.api.crud','record_id'=>'10'];$event='proc_compra_despesas_progress.api.'.($root?'process':$op);
   if($case==='manual')unset($a['calculationId']);
   switch($case){case'foreign':$a['procCompraId']=20;$a['recordId']=103;break;case'tenant':$tx['tenant_id']='b';break;case'unconfirmed':$a['available']=false;break;case'handler':$tx['operation']='other';break;case'screen':$tx['screen_id']='other';break;case'event':$event='other.api.update';break;case'typed-id':$a['recordId']='101';break;case'typed-owner':$a['procCompraId']='10';break;case'typed-calculation':$a['calculationId']='501';break;case'zero-calculation':$a['calculationId']=0;break;case'overflow-calculation':$a['calculationId']=2147483648;break;case'header-identity':$a['recordId']=502;break;case'root-identity':$a['recordId']=11;break;case'tax-delete':$a['operation']='delete';$event='proc_compra_despesas_progress.api.delete';break;case'failed':$tx['status']='failed';break;case'origin':$a['source']='browser';break;case'resource':$a['resource']='other';break;case'root-operation':$a['operation']='generate_expenses';break;case'header-operation':$a['operation']='delete';break;}
   $db->insert('runtime_transaction',$tx);$db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$a])]);
  }return$db;
 }
 public function testFiscalUpdatesAndCreatesAppearOnlyForExactExpense():void{$db=$this->db();self::assertSame([1,4],$this->query($db,'ciw.proc-compra-despesas',101));self::assertSame([5],$this->query($db,'ciw.proc-compra-despesas',102));self::assertSame([],$this->query($db,'ciw.proc-compra-despesas',999));}
 public function testParentIncludesRootHeaderAndEffectsButNeitherItemNorDeletionScopeDoes():void{$db=$this->db();self::assertSame([1,2,3,4,5],$this->query($db,'ciw.processos-compra-med',10));self::assertSame([],$this->query($db,'ciw.proc-compra-itens',11));self::assertSame([],$this->query($db,'ciw.proc-compra-despesas',null,'delete'));self::assertSame([],$this->query($db,'ciw.processos-compra-med',null,'delete'));}
 public function testPostgresChecksClosedFiscalScreenAndTypedCalculationIdentity():void{$db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);$sql=$this->query($db,'ciw.processos-compra-med',10,sql:true);foreach(["'ciw.proc-compra-calcular-impostos'","'calcs_imp_proc_compra'","'calculate_taxes'","'{audit,calculationId}') = 'number'","audit_scope.status = 'succeeded'","2147483647"]as$guard)self::assertStringContainsString($guard,$sql);}
}
