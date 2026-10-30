<?php
namespace App\Tests\Runtime;

use App\Runtime\{RuntimeApiEntityActionService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;

/** Tests the HTTP payload boundary; only the existing external transport is stubbed. */
final class RuntimePurchaseItemNativePayloadTest extends TestCase
{
    private function service(string $op,array &$sent,array &$logs): RuntimeApiEntityActionService
    {
        $fixture=new RuntimePurchaseItemTransportTest('testClosedTransportForAllMutations');
        $method=new \ReflectionMethod($fixture,'service');
        $body=$op==='get'?['success'=>true,'data'=>RuntimePurchaseItemAuditTest::data()]:RuntimePurchaseItemAuditTest::body($op);
        $args=[$op,$body,&$sent,&$logs];
        return $method->invokeArgs($fixture,$args);
    }
    private function execute(string $op,array $payload,array &$sent,array &$logs):array
    {
        return $this->service($op,$sent,$logs)->handle('ciw.proc-compra-itens',$op,['entityCode'=>'proc_compra_itens_progress','operation'=>$op],$payload);
    }
    public function testNativeGetAcceptsEquivalentRootPrimaryKeyWithoutLeakingItToAbl():void
    {
        foreach ([[11,11],['11',11],[11,'11']] as [$id,$alias]) {
            $sent=[];$logs=[];
            $result=$this->execute('get',['id'=>$id,'item_proc_compra_id'=>$alias,'context'=>['proc_compra_id'=>17]],$sent,$logs);
            self::assertSame(11,$result['item_proc_compra_id']);
            self::assertSame([['resource'=>'proc_compra_itens','operation'=>'get','id'=>11,'proc_compra_id'=>17]],$sent);
        }
    }
    public function testNativeGetWithOptionalRecordAcceptsTheSameIdentity():void
    {
        $sent=[];$logs=[];
        $this->execute('get',['id'=>11,'item_proc_compra_id'=>'11','context'=>['proc_compra_id'=>17],'record'=>RuntimePurchaseItemAuditTest::data()],$sent,$logs);
        self::assertSame([['resource'=>'proc_compra_itens','operation'=>'get','id'=>11,'proc_compra_id'=>17]],$sent);
    }
    public function testNativeFlatMutationsUseOnlyClosedWritableAblValues():void
    {
        foreach (['create','update','delete'] as $op) {
            $sent=[];$logs=[];$payload=RuntimePurchaseItemAuditTest::data();
            if ($op==='create') { unset($payload['item_proc_compra_id'],$payload['row_stamp']); }
            if ($op==='delete') { $payload['id']=11; }
            $payload['_runtime']=['version'=>3,'lockToken'=>'native-lock','transactionId'=>42,'actor'=>['username'=>'forged'],'before'=>['descricao'=>'forged']];
            $this->execute($op,$payload,$sent,$logs);
            self::assertCount(1,$sent);
            self::assertSame(17,$sent[0]['proc_compra_id']);
            $confirmed=array_values(array_filter($logs,fn($log)=>$log->getMetadata()['audit']['available']??false));
            self::assertCount(1,$confirmed);
            self::assertSame('real-user',$confirmed[0]->getMetadata()['actor']['username']);
            self::assertSame($op==='create'?[]:RuntimePurchaseItemAuditTest::row(),$confirmed[0]->getBeforeData());
            if ($op==='delete') {
                self::assertSame(['resource'=>'proc_compra_itens','operation'=>'delete','id'=>11,'row_stamp'=>str_repeat('A',64),'proc_compra_id'=>17],$sent[0]);
            } else {
                foreach (['item_proc_compra_id','proc_compra_id','row_stamp','qt_alocada','qt_saldo','calc_gm','_runtime'] as $key) { self::assertArrayNotHasKey($key,$sent[0]['values']); }
                self::assertSame('Item teste',$sent[0]['values']['descricao']);
                self::assertArrayNotHasKey('_runtime',$sent[0]);
                self::assertArrayNotHasKey('item_proc_compra_id',$sent[0]);
            }
        }
    }
    public function testInvalidOrDivergentNativeIdentityNeverReachesExternalTransport():void
    {
        foreach ([12,0,-1,2147483648,11.0,true,null,'','011','11.0','1.1e1',[],['id'=>11]] as $bad) {
            $sent=[];$logs=[];
            try { $this->execute('get',['id'=>11,'item_proc_compra_id'=>$bad,'context'=>['proc_compra_id'=>17]],$sent,$logs);self::fail('Invalid alias accepted: '.get_debug_type($bad)); }
            catch(RuntimeHttpException $e) { self::assertSame(422,$e->getStatusCode()); }
            self::assertSame([],$sent);
        }
        foreach (['id','item_proc_compra_id'] as $key) {
            $sent=[];$logs=[];$record=RuntimePurchaseItemAuditTest::data();$record[$key]=12;
            try { $this->execute('get',['id'=>11,'item_proc_compra_id'=>11,'record'=>$record,'context'=>['proc_compra_id'=>17]],$sent,$logs);self::fail('Divergent nested ID'); }
            catch(RuntimeHttpException $e) { self::assertSame(422,$e->getStatusCode()); }
            self::assertSame([],$sent);
        }
    }
    public function testAmbiguousAndForgedFlatPayloadsFailBeforeExternalTransport():void
    {
        foreach (['unknown','values','actor','context','divergent-root-id','invalid-stamp','mixed-record','mixed-stamp','create-id','create-stamp','nested-parent','nested-runtime'] as $case) {
            $op=str_starts_with($case,'create')?'create':'update';$payload=RuntimePurchaseItemAuditTest::data();
            if ($case==='create-id') { unset($payload['row_stamp']); }
            if ($case==='create-stamp') { unset($payload['item_proc_compra_id']); }
            if ($case==='unknown') { $payload['sql']='free'; }
            if ($case==='values') { $payload['values']=['descricao'=>'free']; }
            if ($case==='actor') { $payload['actor']=['login'=>'forged']; }
            if ($case==='context') { $payload['context']=['proc_compra_id'=>18]; }
            if ($case==='divergent-root-id') { $payload['id']=12; }
            if ($case==='invalid-stamp') { $payload['row_stamp']='forged'; }
            if ($case==='mixed-record'||$case==='mixed-stamp') { $payload['record']=RuntimePurchaseItemAuditTest::data();if($case==='mixed-stamp')$payload['row_stamp']=str_repeat('B',64); }
            if ($case==='nested-parent') { $payload=['id'=>11,'item_proc_compra_id'=>11,'record'=>RuntimePurchaseItemAuditTest::data(),'context'=>['proc_compra_id'=>18]];$op='get'; }
            if ($case==='nested-runtime') { $payload=['id'=>11,'context'=>['proc_compra_id'=>17],'record'=>RuntimePurchaseItemAuditTest::data()+['_runtime'=>['actor'=>'forged']]];$op='get'; }
            $sent=[];$logs=[];
            try { $this->execute($op,$payload,$sent,$logs);self::fail($case); }
            catch(RuntimeHttpException $e) { self::assertSame(422,$e->getStatusCode(),$case); }
            self::assertSame([],$sent,$case);
        }
    }
}
