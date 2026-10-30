<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver,RuntimeHttpException};
use Doctrine\DBAL\{DriverManager,ParameterType};
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/RuntimePurchasePaymentAuditTest.php';
final class RuntimePurchasePaymentLogScopeTest extends TestCase
{
    private function service($db): array {$r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);return [$r,$s];}
    public function testPurchaseLogsSelectOnlyConfirmedLinkAndLedgerWithCorrectParentKeyAndTenant(): void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
        $db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
        foreach(range(1,14) as $i){$audit=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$i===2?'cta_corren_fornec_imp':'purchase_payment_links','operation'=>'create','recordId'=>$i===2?91:RuntimePurchasePaymentAuditTest::key(),'procCompraId'=>17,'paymentKey'=>RuntimePurchasePaymentAuditTest::key()];$screen='ciw.proc-compra-pagamentos';$status='succeeded';$tenant='a';
            switch($i){case 3:$audit['paymentKey']=RuntimePurchasePaymentAuditTest::key(17,8);break;case 4:$audit['procCompraId']=18;$audit['paymentKey']=RuntimePurchasePaymentAuditTest::key(18);break;case 5:$tenant='b';break;case 6:$audit['resource']='containers';break;case 7:$audit['source']='browser';break;case 8:$audit['procCompraId']='17';break;case 9:$audit['paymentKey']=17;break;case 10:$status='failed';break;case 11:$screen='ciw.container-pagamentos';break;case 12:$audit['available']='true';break;case 13:$audit['version']='1';break;case 14:$audit['resource']='proc-compra-pagamentos';break;}
            $db->insert('runtime_transaction',['id'=>$i,'tenant_id'=>$tenant,'screen_id'=>$screen,'status'=>$status,'operation'=>'create','record_id'=>'unrelated']);$db->insert('runtime_transaction_log',['id'=>$i,'transaction_id'=>$i,'event_type'=>'proc_compra_pagamentos_progress.api.create','metadata'=>json_encode(['audit'=>$audit])]);
        }
        [$r,$s]=$this->service($db);
        foreach([['ciw.processos-compra-financeiro-med',17,[1,2,3]],['ciw.proc-compra-pagamentos',RuntimePurchasePaymentAuditTest::key(),[1,2]]] as [$screen,$id,$expected]){$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>$screen,'audit_record_id'=>$id]);self::assertSame($expected,array_map('intval',$q->executeQuery()->fetchFirstColumn()));self::assertSame($screen==='ciw.proc-compra-pagamentos'?ParameterType::STRING:ParameterType::INTEGER,$q->getParameterType('purchaseAuditRecord'));}
    }
    public function testReservedFilterAcceptsOnlyPaymentStringOrPurchaseIntegerForItsScreen(): void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);[$r,$s]=$this->service($db);$m=$r->getMethod('withAuditLogScope');
        foreach([['ciw.proc-compra-pagamentos',RuntimePurchasePaymentAuditTest::key(),true],['ciw.processos-compra-financeiro-med','17',true],['ciw.proc-compra-pagamentos',17,false],['ciw.processos-compra-financeiro-med',RuntimePurchasePaymentAuditTest::key(),false]] as [$screen,$id,$valid]){$payload=['filters'=>[['field'=>'audit_screen_id','operator'=>'eq','value'=>$screen],['field'=>'audit_record_id','operator'=>'eq','value'=>$id]]];try{$out=$m->invoke($s,['entityCode'=>'runtime_transaction_log'],$payload,'read');self::assertTrue($valid);self::assertSame(is_numeric($id)?17:$id,$out['_auditLogScope']['audit_record_id']);}catch(RuntimeHttpException $e){self::assertFalse($valid);self::assertSame('AUDIT_LOG_SCOPE_INVALID',$e->getErrorCode());}}
    }
    public function testPostgresQueryKeepsTypedIdentitiesClosedResourcesAndTenantWithoutDatabaseConnection(): void
    {
        $db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);[$r,$s]=$this->service($db);
        foreach([['ciw.proc-compra-pagamentos',RuntimePurchasePaymentAuditTest::key(),'paymentKey'],['ciw.processos-compra-financeiro-med',17,'procCompraId']] as [$screen,$id,$field]){$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>$screen,'audit_record_id'=>$id]);$sql=$q->getSQL();self::assertStringContainsString('audit_scope.tenant_id = :purchaseAuditTenant',$sql);self::assertSame('a',$q->getParameter('purchaseAuditTenant'));self::assertStringContainsString("'{audit,$field}') = :purchaseAuditRecord",$sql);self::assertStringContainsString("jsonb_typeof(CAST(t.\"metadata\" AS jsonb) #> '{audit,procCompraId}') = 'number'",$sql);self::assertStringContainsString("IN ('purchase_payment_links','cta_corren_fornec_imp')",$sql);self::assertStringContainsString("audit_scope.screen_id = 'ciw.proc-compra-pagamentos'",$sql);}
    }
}


