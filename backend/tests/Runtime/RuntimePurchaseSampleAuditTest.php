<?php
namespace App\Tests\Runtime;

use App\Runtime\RuntimePurchaseSampleAudit;
use App\Runtime\RuntimePurchaseContextAudit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimePurchaseSampleAuditTest extends TestCase
{
    private static function definition(): array { return ['entityCode'=>'proc_compra_amostras_progress','primaryKey'=>'envio_amostra_repres_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs']]; }
    private static function endpoint(string $op='create'): array { return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','requestContextFields'=>['proc_compra_id','item_proc_compra_id'],'bodyTemplate'=>['resource'=>'proc_compra_amostras','operation'=>$op]]; }
    private static function image(): array { return ['envio_amostra_repres_id'=>101,'item_proc_compra_id'=>11,'cod_rep'=>7,'dt_envio'=>'2026-10-22','apont_amostra_id'=>99,'proc_compra_id'=>10]; }
    private static function body(): array { $row=self::image(); return ['success'=>true,'committed'=>true,'data'=>$row+['row_stamp'=>str_repeat('A',64),'representante_nome'=>'Rep7'],'audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'envios-amostras-repres','operation'=>'create','recordId'=>101,'actor'=>['login'=>'teste'],'before'=>[],'after'=>$row,'changes'=>[],'warnings'=>[]]]; }
    private static function inspect(array $body): array { return RuntimePurchaseSampleAudit::inspect(self::definition(),self::endpoint(),$body,'create',101,['proc_compra_id'=>10,'item_proc_compra_id'=>11],'teste'); }
    public function testCommittedNativeAuditIncludesEveryPhysicalFieldAndActualOwners(): void
    {
        $audit=self::inspect(self::body());
        self::assertTrue($audit['metadata']['available']);self::assertSame([], $audit['before']);self::assertSame(self::image(),$audit['after']);
        self::assertSame('envios_amostras_repres',$audit['metadata']['resource']);self::assertSame(10,$audit['metadata']['procCompraId']);self::assertSame(11,$audit['metadata']['itemProcCompraId']);self::assertSame(101,$audit['metadata']['recordId']);
    }
    public function testSharedPurchaseTransportSelectsOnlyExplicitProfiles(): void
    {
        self::assertTrue(RuntimePurchaseContextAudit::supports(self::definition()));
        self::assertTrue(RuntimePurchaseContextAudit::supports(['entityCode'=>'proc_compra_itens_progress']));
        self::assertFalse(RuntimePurchaseContextAudit::supports(['entityCode'=>'unknown']));
        self::assertTrue(RuntimePurchaseContextAudit::editableField('dt_envio',self::definition()));
        self::assertFalse(RuntimePurchaseContextAudit::editableField('apont_amostra_id',self::definition()));
        self::assertFalse(RuntimePurchaseContextAudit::editableField('cod_rep',['entityCode'=>'proc_compra_itens_progress']));
    }
    public function testBindingRequiresExactRouteAndBothContextFields(): void
    {
        self::assertTrue(RuntimePurchaseSampleAudit::binding(self::definition(),self::endpoint(),'create'));
        foreach ([['requestContextFields'=>['proc_compra_id']],['url'=>'http://evil.invalid/SursumPrograms/ciw-cadastros'],['bodyTemplate'=>['resource'=>'proc_compra_itens','operation'=>'create']],['method'=>'GET']] as $delta) { self::assertFalse(RuntimePurchaseSampleAudit::binding(self::definition(),array_replace(self::endpoint(),$delta),'create')); }
    }
    public function testNullableDateAndHiddenLinkArePreservedInUpdateAndDelete(): void
    {
        $body=self::body();$body['audit']['operation']='update';$body['audit']['before']=self::image();$body['audit']['after']['dt_envio']=null;$body['data']['dt_envio']=null;
        $audit=RuntimePurchaseSampleAudit::inspect(self::definition(),self::endpoint('update'),$body,'update',101,['proc_compra_id'=>10,'item_proc_compra_id'=>11],'teste');
        self::assertTrue($audit['metadata']['available']);self::assertSame(99,$audit['after']['apont_amostra_id']);self::assertNull($audit['after']['dt_envio']);
        $body['audit']['operation']='delete';$body['audit']['after']=[];$body['data']=['envio_amostra_repres_id'=>101];
        self::assertTrue(RuntimePurchaseSampleAudit::inspect(self::definition(),self::endpoint('delete'),$body,'delete',101,['proc_compra_id'=>10,'item_proc_compra_id'=>11],'teste')['metadata']['available']);
    }
    public static function badBodies(): iterable
    {
        foreach (['commit','actor','missingPhysical','foreignItem','foreignParent','badDate','wrongData','extraEffect','wrongResource','extraField'] as $name) { yield $name=>[$name]; }
    }
    #[DataProvider('badBodies')]
    public function testUntrustedIncompleteOrForeignAuditIsUnavailable(string $case): void
    {
        $body=self::body();
        switch ($case) {
            case 'commit':$body['committed']=false;break;
            case 'actor':$body['audit']['actor']['login']='forged';break;
            case 'missingPhysical':unset($body['audit']['after']['apont_amostra_id']);break;
            case 'foreignItem':$body['audit']['after']['item_proc_compra_id']=12;break;
            case 'foreignParent':$body['audit']['after']['proc_compra_id']=20;break;
            case 'badDate':$body['audit']['after']['dt_envio']='2026-02-30';break;
            case 'wrongData':$body['data']['cod_rep']=8;break;
            case 'extraEffect':$body['audit']['changes']=[['operation'=>'delete']];break;
            case 'wrongResource':$body['audit']['resource']='itens-proc-compra';break;
            case 'extraField':$body['audit']['after']['login']='forged';break;
        }
        self::assertFalse(self::inspect($body)['metadata']['available']);
    }
}
