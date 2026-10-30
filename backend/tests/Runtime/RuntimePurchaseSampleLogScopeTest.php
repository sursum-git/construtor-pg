<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseSampleLogScopeTest extends TestCase
{
    private function service($db):array { $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);return [$r,$s]; }
    private function query($db,array $scope):array { [$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,$scope);return array_map('intval',$q->executeQuery()->fetchFirstColumn()); }
    private function db()
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
        $cases=['update','delete','tenant','screen','failed','handler','available','source','version','typed-id','typed-item','typed-parent','resource','event','operation','range'];
        foreach($cases as $i=>$case){$id=$i+1;$op=$case==='update'?'update':'delete';$audit=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'envios_amostras_repres','operation'=>$op,'recordId'=>101,'procCompraId'=>10,'itemProcCompraId'=>11];$tx=['id'=>$id,'tenant_id'=>'a','screen_id'=>'ciw.proc-compra-amostras','status'=>'succeeded','operation'=>'entity.api.crud','record_id'=>'101'];$event='proc_compra_amostras_progress.api.'.$op;
            switch($case){case'tenant':$tx['tenant_id']='b';break;case'screen':$tx['screen_id']='other';break;case'failed':$tx['status']='failed';break;case'handler':$tx['operation']='delete';break;case'available':$audit['available']=false;break;case'source':$audit['source']='browser';break;case'version':$audit['version']='1';break;case'typed-id':$audit['recordId']='101';break;case'typed-item':$audit['itemProcCompraId']='11';break;case'typed-parent':$audit['procCompraId']='10';break;case'resource':$audit['resource']='itens_proc_compra';break;case'event':$event='other.api.delete';break;case'operation':$audit['operation']='update';break;case'range':$audit['recordId']=2147483648;break;}
            $db->insert('runtime_transaction',$tx);$db->insert('runtime_transaction_log',['id'=>$id,'transaction_id'=>$id,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$audit])]);
        }return $db;
    }
    public function testSampleRecordAndDeletionScopesKeepAllGuards():void
    { $db=$this->db();self::assertSame([1,2],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-amostras','audit_record_id'=>101]));self::assertSame([2],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-amostras','audit_operation'=>'delete']));self::assertSame([],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-amostras','audit_record_id'=>102])); }
    public function testItemsAndOrdersAggregateSampleEventsButNotAsTheirOwnDeletions():void
    { $db=$this->db();self::assertSame([1,2],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_record_id'=>11]));self::assertSame([1,2],$this->query($db,['audit_screen_id'=>'ciw.processos-compra-med','audit_record_id'=>10]));self::assertSame([],$this->query($db,['audit_screen_id'=>'ciw.proc-compra-itens','audit_operation'=>'delete'])); }
    public function testPostgresSampleScopeUsesTypedIdentityAndNativeHandler():void
    { $db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);[$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.proc-compra-amostras','audit_operation'=>'delete']);$sql=$q->getSQL();foreach(["audit_scope.operation = 'entity.api.crud'","audit_scope.screen_id = 'ciw.proc-compra-amostras'","audit_scope.tenant_id = :sampleAuditTenant","audit_scope.status = 'succeeded'","'{audit,recordId}') = 'number'","'envios_amostras_repres'","'proc_compra_amostras_progress.api.'","2147483647"] as $guard){self::assertStringContainsString($guard,$sql);}self::assertSame('a',$q->getParameter('sampleAuditTenant')); }
}
