<?php
namespace App\Runtime;

/** Shared contextual purchase transport profiles. No table rules or formulas live here. */
final class RuntimePurchaseContextAudit
{
    private static function profile(array $definition): ?string
    { return match ($definition['entityCode']??null) { 'proc_compra_despesas_progress'=>RuntimePurchaseExpenseAudit::class,'proc_compra_itens_progress'=>RuntimePurchaseItemAudit::class,'proc_compra_amostras_progress'=>RuntimePurchaseSampleAudit::class,'proc_compra_anexos_progress'=>RuntimePurchaseAttachmentAudit::class,'proc_compra_documentos_progress'=>RuntimePurchaseAttachmentAudit::class,default=>null }; }
    public static function child(array $definition):bool { return RuntimePurchaseExpenseAudit::supports($definition)||RuntimePurchaseSampleAudit::supports($definition)||RuntimePurchaseAttachmentAudit::supports($definition); }
    public static function supports(array $definition): bool { return self::profile($definition)!==null; }
    public static function binding(array $definition,array $endpoint,string $operation): bool { $profile=self::profile($definition);return $profile!==null && $profile::binding($definition,$endpoint,$operation); }
    public static function editableField(string $field,array $definition): bool { $profile=self::profile($definition);return $profile!==null && $profile::editableField($field); }
    public static function dataRow(mixed $row,array $definition): bool { $profile=self::profile($definition);return $profile===RuntimePurchaseAttachmentAudit::class ? RuntimePurchaseAttachmentAudit::dataRow($row,$definition) : ($profile!==null && $profile::dataRow($row)); }
    public static function matchesContext(array $row,array $context): bool
    { $ids=array_intersect_key($context,['proc_compra_id'=>true,'item_proc_compra_id'=>true]);if(!$ids){return false;}foreach($ids as $key=>$value){if(($row[$key]??null)!==$value){return false;}}return true; }
    public static function inspect(array $definition,array $endpoint,array $body,string $operation,mixed $id,mixed $context,?string $actor): array
    { $profile=self::profile($definition);return $profile!==null ? $profile::inspect($definition,$endpoint,$body,$operation,$id,$context,$actor) : ['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>'unsupported_binding']]; }
}
