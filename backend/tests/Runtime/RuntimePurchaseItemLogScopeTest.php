<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseItemLogScopeTest extends TestCase {
 private function service($db):array {$r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);return [$r,$s];}
 private function db(){ $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');foreach(range(1,13)as $i){$a=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'cta_corren_fornec_imp','operation'=>'create','recordId'=>90+$i,'procCompraId'=>18,'itemProcCompraId'=>12,'originProcCompraId'=>17,'originItemProcCompraId'=>11];$tenant='a';$screen='ciw.proc-compra-itens';$status='succeeded';$event='proc_compra_itens_progress.api.create';$op='entity.api.crud';if($i===1||$i===2){$a['resource']='itens_proc_compra';$a['recordId']=$a['itemProcCompraId']=11;$a['procCompraId']=17;$a['operation']=$i===1?'update':'delete';if($i===2){$event='proc_compra_itens_progress.api.delete';}}if($i===4){$a['resource']='containers';unset($a['procCompraId'],$a['itemProcCompraId']);}if($i===5)$tenant='other';if($i===6)$status='failed';if($i===7)$screen='other';if($i===8)$a['originItemProcCompraId']=13;if($i===9)$a['resource']='unknown';if($i===10)$a['originItemProcCompraId']='11';if($i===11)$a['available']=false;if($i===12){$a['originProcCompraId']=19;}if($i===13){$a['resource']='itens_proc_compra';$a['operation']='delete';$a['recordId']=12;}$db->insert('runtime_transaction',['id'=>$i,'tenant_id'=>$tenant,'screen_id'=>$screen,'status'=>$status,'operation'=>$op,'record_id'=>'11']);$db->insert('runtime_transaction_log',['id'=>$i,'transaction_id'=>$i,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$a])]);}return $db;}
 private function query($db,array $scope):array{[$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,$scope);return array_map('intval',$q->executeQuery()->fetchFirstColumn());}
 public function testItemLogsIncludeActualOrOriginWithTenantAndResourceClosure():void{$db=$this->db();self::assertSame([1,2,3,4,12],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_record_id'=>11]));self::assertSame([1,2,3,4],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_record_id'=>11,'audit_proc_compra_id'=>17]));}
 public function testDeletionNavigationOnlyShowsItemDeleteNeverCollateralEffects():void{$db=$this->db();self::assertSame([2],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete']));self::assertSame([2],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete','audit_proc_compra_id'=>17]));}
 public function testParentIncludesItemScreenEffectsByActualOrOrigin():void{$db=$this->db();self::assertSame([1,2,3,4,8],$this->query($db,['audit_screen_id'=>'ciw.processos-compra-med','audit_record_id'=>17]));}
 public function testPostgresScopeHasClosedTenantAndTypedOrigin():void{$db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);[$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.proc-compra-itens','audit_record_id'=>11]);self::assertStringContainsString('tenant_id = :itemAuditTenant',$q->getSQL());self::assertStringContainsString("'{audit,originItemProcCompraId}') = 'number'",$q->getSQL());}

 /** RuntimeTransaction.operation stores the handler, while audit.operation stores the CRUD verb. */
 public function testRealDeletionHandlerKeepsAllAuditGuardsClosed():void
 {
  $base=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'itens_proc_compra','operation'=>'delete','recordId'=>11,'procCompraId'=>17,'itemProcCompraId'=>11,'originProcCompraId'=>17,'originItemProcCompraId'=>11];
  $cases=['valid','verb-as-handler','readonly-handler','other-handler','tenant','screen','failed','event','collateral','crud-verb','available','available-string','version','version-string','source','typed-id','typed-parent','typed-origin','wrong-item-origin','wrong-parent-origin','wrong-record','foreign-parent'];
  $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
  $db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');
  $db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
  foreach($cases as $index=>$case){
   $id=$index+1;$audit=$base;
   $tx=['id'=>$id,'tenant_id'=>'a','screen_id'=>'ciw.proc-compra-itens','status'=>'succeeded','operation'=>'entity.api.crud','record_id'=>'11'];
   $event='proc_compra_itens_progress.api.delete';
   switch($case){
    case 'verb-as-handler':$tx['operation']='delete';break;
    case 'readonly-handler':$tx['operation']='entity.api.readonly';break;
    case 'other-handler':$tx['operation']='entity.crud';break;
    case 'tenant':$tx['tenant_id']='other';break;
    case 'screen':$tx['screen_id']='other';break;
    case 'failed':$tx['status']='failed';break;
    case 'event':$event='other.api.delete';break;
    case 'collateral':$audit['resource']='cta_corren_fornec_imp';break;
    case 'crud-verb':$audit['operation']='update';break;
    case 'available':$audit['available']=false;break;
    case 'available-string':$audit['available']='true';break;
    case 'version':$audit['version']=2;break;
    case 'version-string':$audit['version']='1';break;
    case 'source':$audit['source']='browser';break;
    case 'typed-id':$audit['recordId']='11';break;
    case 'typed-parent':$audit['procCompraId']='17';break;
    case 'typed-origin':$audit['originItemProcCompraId']='11';break;
    case 'wrong-item-origin':$audit['originItemProcCompraId']=12;break;
    case 'wrong-parent-origin':$audit['originProcCompraId']=18;break;
    case 'wrong-record':$audit['recordId']=12;break;
    case 'foreign-parent':$audit['procCompraId']=$audit['originProcCompraId']=18;break;
   }
   $db->insert('runtime_transaction',$tx);
   $db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$audit])]);
  }
  self::assertSame([1],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete','audit_proc_compra_id'=>17]));
  self::assertSame([1],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete','audit_record_id'=>11,'audit_proc_compra_id'=>17]));
  self::assertSame([],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete','audit_record_id'=>12,'audit_proc_compra_id'=>17]));
 }
 public function testPostgresDeletionUsesNativeHandlerAndClosedAuditPredicate():void
 {
  $db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);[$r,$s]=$this->service($db);
  $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t');
  $r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete','audit_proc_compra_id'=>17]);
  $sql=$q->getSQL();
  foreach(["audit_scope.operation = 'entity.api.crud'","audit_scope.screen_id = 'ciw.proc-compra-itens'","audit_scope.status = 'succeeded'","audit_scope.tenant_id = :itemAuditTenant","'proc_compra_itens_progress.api.delete'","'{audit,resource}') = 'itens_proc_compra'","'{audit,operation}') = 'delete'","'{audit,available}' = 'true'::jsonb","'{audit,version}' = '1'::jsonb","'{audit,source}') = 'progress-transaction'","'{audit,originItemProcCompraId}') = 'number'"] as $guard){self::assertStringContainsString($guard,$sql);}
  self::assertSame('a',$q->getParameter('itemAuditTenant'));
  self::assertSame('17',$q->getParameter('itemAuditParent'));
 }
}
