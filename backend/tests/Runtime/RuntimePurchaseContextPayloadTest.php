<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimePurchaseContextPayload,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseContextPayloadTest extends TestCase
{
    private const SAMPLE=['entityCode'=>'proc_compra_amostras_progress','primaryKey'=>'envio_amostra_repres_id'];
    public function testNativeFlatSampleUsesBothReadonlyOwnersAndPreservesStamp():void
    {
        $result=RuntimePurchaseContextPayload::normalize(self::SAMPLE,'update',['envio_amostra_repres_id'=>101,'item_proc_compra_id'=>11,'proc_compra_id'=>10,'cod_rep'=>7,'dt_envio'=>null,'apont_amostra_id'=>99,'representante_nome'=>'Rep7','row_stamp'=>str_repeat('A',64)]);
        self::assertSame(101,$result['id']);self::assertSame(['proc_compra_id'=>10,'item_proc_compra_id'=>11],$result['context']);self::assertSame(99,$result['record']['apont_amostra_id']);
    }
    public function testEquivalentGetAliasesAreAcceptedAndRemovedOnlyForTheSelectedEntity():void
    {
        $result=RuntimePurchaseContextPayload::normalize(self::SAMPLE,'get',['id'=>101,'envio_amostra_repres_id'=>'101','context'=>['proc_compra_id'=>10,'item_proc_compra_id'=>11]]);
        self::assertSame(101,$result['id']);self::assertArrayNotHasKey('envio_amostra_repres_id',$result);
        $item=RuntimePurchaseContextPayload::normalize(['entityCode'=>'proc_compra_itens_progress','primaryKey'=>'item_proc_compra_id'],'get',['id'=>11,'item_proc_compra_id'=>'11','context'=>['proc_compra_id'=>10]]);
        self::assertSame(11,$item['id']);self::assertArrayNotHasKey('item_proc_compra_id',$item);
    }
    public function testContradictoryOwnersAliasesUnknownFieldsAndMixedFormsFailClosed():void
    {
        $flat=['envio_amostra_repres_id'=>101,'item_proc_compra_id'=>11,'proc_compra_id'=>10,'cod_rep'=>7,'row_stamp'=>str_repeat('A',64)];
        foreach ([$flat+['context'=>['proc_compra_id'=>10,'item_proc_compra_id'=>12]],$flat+['id'=>102],$flat+['actor'=>'forged'],$flat+['values'=>['cod_rep'=>8]],$flat+['record'=>$flat]] as $bad) {
            try { RuntimePurchaseContextPayload::normalize(self::SAMPLE,'update',$bad);self::fail('Ambiguous payload accepted'); }
            catch(RuntimeHttpException $e) { self::assertSame(422,$e->getStatusCode()); }
        }
    }
}
