<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwErpQuery,RuntimeApiEntityActionService,RuntimeExternalJsonClient,RuntimeTransactionService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseReadTest extends TestCase
{
    private function definition(): array { return ['entityCode'=>'procs_compra_financeiro_med_progress','primaryKey'=>'proc_compra_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs','authHeaders'=>[],'listEndpoint'=>$this->endpoint()],'fields'=>['proc_compra_id'=>['code'=>'proc_compra_id','jsonPath'=>'proc_compra_id','dataType'=>'integer','collection'=>null],'record_key'=>['code'=>'record_key','jsonPath'=>'record_key','dataType'=>'string','collection'=>null]]]; }
    private function endpoint(): array { return ['method'=>'POST','url'=>'http://127.0.0.1:19910/SursumPrograms/ciw-cadastros','bodyTemplate'=>['resource'=>'procs_compra_financeiro_med','operation'=>'read']]; }
    public function testParentUsesSpecificIntegerIdentityAndExistingServerPaging(): void
    {
        $d=$this->definition();self::assertTrue(RuntimeCiwErpQuery::supports($d));$p=RuntimeCiwErpQuery::prepare($d,$this->endpoint(),['skip'=>40,'take'=>20,'filter'=>['field'=>'proc_compra_id','operator'=>'eq','value'=>17]]);self::assertSame(40,$p['skip']);
        $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturn(['status'=>200,'body'=>['success'=>true,'data'=>[['proc_compra_id'=>17,'record_key'=>'{"proc_compra_id":17}']],'total'=>41]]);
        $r=new \ReflectionClass(RuntimeApiEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('externalJsonClient')->setValue($s,$client);$r->getProperty('transactions')->setValue($s,$this->createStub(RuntimeTransactionService::class));$out=$r->getMethod('read')->invoke($s,$d,['skip'=>40,'take'=>20]);self::assertSame(41,$out['total']);self::assertSame(17,$out['data'][0]['proc_compra_id']);
    }
    public function testParentCannotOptIntoWrongKeyOrBrowserCompanyContext(): void
    {
        foreach([['primaryKey'=>'record_key'],[]] as $delta){$d=array_replace($this->definition(),$delta);try{RuntimeCiwErpQuery::prepare($d,$this->endpoint(),$delta?[]:['context'=>['proc_compra_id'=>17]]);self::fail('Invalid parent accepted');}catch(RuntimeHttpException $e){self::assertSame('CIW_ERP_QUERY_INVALID',$e->getErrorCode());}}
    }
    public function testParentResponseRequiresItsTypedPositivePrimaryKey(): void
    {
        $client=$this->createStub(RuntimeExternalJsonClient::class);$client->method('request')->willReturn(['status'=>200,'body'=>['success'=>true,'data'=>[['proc_compra_id'=>'17','record_key'=>'{"proc_compra_id":17}']],'total'=>1]]);
        $r=new \ReflectionClass(RuntimeApiEntityActionService::class);$s=$r->newInstanceWithoutConstructor();$r->getProperty('externalJsonClient')->setValue($s,$client);$r->getProperty('transactions')->setValue($s,$this->createStub(RuntimeTransactionService::class));$this->expectException(RuntimeHttpException::class);$r->getMethod('read')->invoke($s,$this->definition(),[]);
    }
}
