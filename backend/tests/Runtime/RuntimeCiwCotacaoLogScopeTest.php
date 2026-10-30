<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
final class RuntimeCiwCotacaoLogScopeTest extends TestCase
{
    public function testRecordLogsIncludeOnlyConfirmedBcImportForSameTenantAndRecord(): void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');
        $db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
        foreach(range(1,8) as $i){
            $db->insert('runtime_transaction',['id'=>$i,'tenant_id'=>$i===3?'other':'a','screen_id'=>$i===8?'ciw.other':'ciw.cotacoes-bc-buscar','status'=>$i===4?'failed':'succeeded','operation'=>'process','record_id'=>'10']);
            $audit=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'hist_cotacoes_bc','operation'=>'import-bc','recordId'=>$i===2?11:10];
            if($i===5)$audit['resource']='other';if($i===6)$audit['available']=false;if($i===7)$audit['operation']='delete';
            $db->insert('runtime_transaction_log',['id'=>$i,'transaction_id'=>$i,'event_type'=>'hist_cotacoes_bc_progress.api.process','metadata'=>json_encode(['audit'=>$audit])]);
        }
        $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);
        $p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);
        $q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');
        $r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.cotacoes-bc','audit_record_id'=>10]);
        self::assertSame([1],array_map('intval',$q->executeQuery()->fetchFirstColumn()));
    }
}
