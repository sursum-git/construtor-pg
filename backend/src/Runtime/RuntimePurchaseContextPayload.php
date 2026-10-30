<?php
namespace App\Runtime;

/** One native payload normalizer for closed item/shipment profiles. */
final class RuntimePurchaseContextPayload
{
    public static function normalize(array $definition,string $operation,array $payload): array
    {
        $invalid=static function(): never { throw new RuntimeHttpException('PURCHASE_ITEM_PAYLOAD_INVALID','Parametros ou identidades conflitantes no contexto de compra.',422); };
        $expense=RuntimePurchaseExpenseAudit::supports($definition);
        $sample=RuntimePurchaseSampleAudit::supports($definition);
        $attachment=RuntimePurchaseAttachmentAudit::supports($definition);
        if (!$expense && !$sample && !$attachment && !RuntimePurchaseItemAudit::supports($definition)) { $invalid(); }
        $key=$expense?'despesa_proc_compra_id':($attachment?'anexo_proc_compra_id':($sample?'envio_amostra_repres_id':'item_proc_compra_id'));
        $parents=$attachment?RuntimePurchaseAttachmentAudit::contextFields($definition):($sample?['proc_compra_id','item_proc_compra_id']:['proc_compra_id']);
        $projection=$expense?['calc_imp_proc_compra_id','despesa_descricao']:($attachment?['item_proc_compra_id','proc_compra_id_registro','tipo_anexo_descricao','caminho_arquivo','dt_hr_arquivo']:($sample?['apont_amostra_id','representante_nome']:['qt_alocada','qt_saldo','calc_gm']));
        $hasRecord=array_key_exists('record',$payload);
        if ($hasRecord && !is_array($payload['record'])) { $invalid(); }
        $record=$hasRecord?$payload['record']:[];$flat=false;
        foreach (array_keys($payload) as $name) {
            $known=RuntimePurchaseContextAudit::editableField((string)$name,$definition) || in_array($name,array_merge([$key,'row_stamp'],$parents,$projection),true);
            if (!$known) { continue; }
            if ($operation==='get' || $hasRecord) { if ($name!==$key) { $invalid(); } continue; }
            $flat=true;$record[$name]=$payload[$name];unset($payload[$name]);
        }
        if ($flat) {
            $payload['record']=$record;
            if (!array_key_exists('context',$payload)) {
                $context=[];foreach ($parents as $parent) { if (array_key_exists($parent,$record)) { $context[$parent]=$record[$parent]; } }
                if ($context) { $payload['context']=$context; }
            }
        }
        $identity=null;
        foreach ([$payload,$record] as $source) {
            foreach (['id',$key] as $name) {
                if (!array_key_exists($name,$source)) { continue; }
                $value=$source[$name];if ($operation==='create' && ($value===null || $value==='')) { continue; }
                $id=RuntimePurchaseOrderAudit::recordId($value);
                if ($operation==='create' || $id===null || $identity!==null && $identity!==$id) { $invalid(); }
                $identity=$id;
            }
        }
        if ($identity!==null) { $payload['id']=$identity; }unset($payload[$key]);
        if (array_diff(array_keys($payload),['record','id','context','entityCode','operation','actionId','programId','permissionPrefix','traceability','_runtime','_runtimeEndpoint','runtime'])) { $invalid(); }
        foreach ($parents as $parent) {
            if (!array_key_exists($parent,$record)) { continue; }
            $id=RuntimePurchaseOrderAudit::recordId($record[$parent]);
            if ($id===null || !is_array($payload['context']??null) || RuntimePurchaseOrderAudit::recordId($payload['context'][$parent]??null)!==$id) { $invalid(); }
        }
        return $payload;
    }
}
