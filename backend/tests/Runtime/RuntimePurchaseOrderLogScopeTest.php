<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeEntityActionService,PermissionResolver};
use Doctrine\DBAL\{DriverManager,ParameterType};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseOrderLogScopeTest extends TestCase
{
    private function service($db): array { $r=new \ReflectionClass(RuntimeEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('connection')->setValue($s,$db);$p=$this->createStub(PermissionResolver::class);$p->method('getTenantId')->willReturn('a');$r->getProperty('permissions')->setValue($s,$p);return [$r,$s]; }
    private function database() {
        $db=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);$db->executeStatement('CREATE TABLE runtime_transaction(id INTEGER,tenant_id TEXT,screen_id TEXT,status TEXT,operation TEXT,record_id TEXT)');$db->executeStatement('CREATE TABLE runtime_transaction_log(id INTEGER,transaction_id INTEGER,event_type TEXT,metadata TEXT)');
        foreach(range(1,19) as $i){$screen='ciw.processos-compra-med';$tenant='a';$status='succeeded';$operation='update';$event='procs_compra_med_progress.api.update';$a=['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'cta_corren_fornec_imp','operation'=>'create','recordId'=>90+$i,'procCompraId'=>17,'originProcCompraId'=>17];
            switch($i){case 1:$a['resource']='procs_compra';$a['recordId']=17;$a['operation']='update';break;case 3:$a['procCompraId']=18;break;case 4:$a['originProcCompraId']=18;break;case 5:case 6:case 15:$screen='ciw.proc-compra-pagamentos';$a['resource']=$i===6?'cta_corren_fornec_imp':'purchase_payment_links';$a['paymentKey']='p_'.str_repeat('A',64);$a['recordId']=$i===6?96:$a['paymentKey'];unset($a['originProcCompraId']);if($i===15)$a['operation']='delete';break;case 7:$tenant='other';break;case 8:$screen='ciw.other';break;case 9:$a['resource']='containers';break;case 10:$a['available']=false;$status='failed';break;case 11:$a['procCompraId']=$a['originProcCompraId']=20;break;case 12:$a['procCompraId']='17';break;case 13:$a['originProcCompraId']='17';break;case 14:$a['resource']='procs_compra';$a['recordId']=17;$a['operation']=$operation='delete';$event='procs_compra_med_progress.api.delete';break;case 16:$a['operation']='delete';$event='procs_compra_med_progress.api.delete';break;case 17:$a['resource']='procs_compra';$a['recordId']=18;break;case 18:$a['resource']='procs_compra';$a['recordId']=17;$a['originProcCompraId']=18;break;case 19:$a['available']=false;$operation='delete';$event='procs_compra_med_progress.api.delete';break;}
            $db->insert('runtime_transaction',['id'=>$i,'tenant_id'=>$tenant,'screen_id'=>$screen,'status'=>$status,'operation'=>$operation,'record_id'=>'17']);$db->insert('runtime_transaction_log',['id'=>$i,'transaction_id'=>$i,'event_type'=>$event,'metadata'=>json_encode(['audit'=>$a])]);
        }return $db;
    }
    public function testHeaderAndFinancialParentIncludeActualAndOriginEffectsAndPaymentsOnlyInTheSameTenant(): void {
        $db=$this->database();[$r,$s]=$this->service($db);
        foreach([['ciw.processos-compra-med',17,[1,2,3,4,5,6,10,14,15,16,19]],['ciw.processos-compra-financeiro-med',17,[1,2,3,4,5,6,14,15,16]],['ciw.processos-compra-med',18,[3,4]]]as [$screen,$id,$expected]){$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>$screen,'audit_record_id'=>$id]);self::assertSame($expected,array_map('intval',$q->executeQuery()->fetchFirstColumn()));self::assertSame(ParameterType::INTEGER,$q->getParameterType('purchaseAuditRecord'));}
    }
    public function testHeaderDeletionViewerExcludesPaymentUnlinksAndPhysicalLedgerDeletesEvenWithoutRecordFilter(): void {
        $db=$this->database();[$r,$s]=$this->service($db);foreach([[],['audit_record_id'=>17]]as $record){$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.processos-compra-med','audit_operation'=>'delete']+$record);self::assertSame([14,19],array_map('intval',$q->executeQuery()->fetchFirstColumn()));}
    }
    public function testPaymentKeyScopesDoNotImportUnrelatedHeaderEvents(): void {
        $db=$this->database();[$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t')->orderBy('t.id');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.proc-compra-pagamentos','audit_record_id'=>'p_'.str_repeat('A',64)]);self::assertSame([5,6,15],array_map('intval',$q->executeQuery()->fetchFirstColumn()));self::assertSame(ParameterType::STRING,$q->getParameterType('purchaseAuditRecord'));
    }
    public function testPostgresParentQueryRequiresNumericActualAndOriginAndNeverDropsTheTenant(): void {
        $db=DriverManager::getConnection(['driver'=>'pdo_pgsql','serverVersion'=>'16.0']);[$r,$s]=$this->service($db);$q=$db->createQueryBuilder()->select('t.id')->from('runtime_transaction_log','t');$r->getMethod('applyAuditLogScope')->invoke($s,$q,['audit_screen_id'=>'ciw.processos-compra-med','audit_record_id'=>17]);$sql=$q->getSQL();self::assertStringContainsString('audit_scope.tenant_id = :purchaseAuditTenant',$sql);self::assertStringContainsString("'{audit,originProcCompraId}') = 'number'",$sql);self::assertStringContainsString("audit_scope.screen_id = 'ciw.processos-compra-med'",$sql);self::assertSame('a',$q->getParameter('purchaseAuditTenant'));
    }
}
