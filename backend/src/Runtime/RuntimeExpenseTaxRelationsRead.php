<?php
namespace App\Runtime;

/** Global physical relation rows. Optional integers are not invented positive foreign keys. */
final class RuntimeExpenseTaxRelationsRead
{
    private const KEYS=['despesa_id','imposto_id','empresa_id'];
    public static function supports(array$d):bool{return($d['entityCode']??null)==='despesas_impostos_geral_progress';}
    private static function fail(int$status=502):never{throw new RuntimeHttpException('EXPENSE_TAX_RELATIONS_READ_INVALID','Consulta global de relacoes despesa/imposto invalida ou indisponivel.',$status);}
    public static function operation(string$operation):void{if(!in_array($operation,['read','get'],true))self::fail(405);}
    private static function physical(mixed$v):bool{return$v===null||is_int($v)&&$v>=-2147483648&&$v<=2147483647;}
    private static function key(mixed$id,int$status):array
    {
        if(!is_string($id)||$id===''||strlen($id)>2048)self::fail($status);
        try{$key=json_decode($id,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::fail($status);}
        if(!RuntimeProgressAuditSchema::exact($key,self::KEYS))self::fail($status);
        $canonical=[];foreach(self::KEYS as$name){if(!self::physical($key[$name]))self::fail($status);$canonical[$name]=$key[$name];}
        if(json_encode($canonical,JSON_THROW_ON_ERROR)!==$id)self::fail($status);
        return$canonical;
    }
    private static function binding(array$d,array$endpoint,string$operation):void
    {
        if(!RuntimeProgressAuditSchema::ciwBinding($d,$endpoint,'despesas_impostos_geral_progress','record_key','despesas_impostos_geral',$operation,['read','get'])||($endpoint['requestContextFields']??[])!==[])self::fail(422);
    }
    public static function readRequest(array$d,array$endpoint,array$payload):void
    {
        self::binding($d,$endpoint,'read');
        if(array_key_exists('context',$payload)&&!in_array($payload['context'],[[],null],true))self::fail(422);
    }
    private static function row(mixed$row):void
    {
        if(!RuntimeProgressAuditSchema::exact($row,[...self::KEYS,'record_key']))self::fail();
        $key=self::key($row['record_key'],502);
        foreach(self::KEYS as$name)if(!self::physical($row[$name])||$row[$name]!==$key[$name])self::fail();
    }
    public static function readResponse(array$body):void
    {
        if(!RuntimeProgressAuditSchema::exact($body,['success','data','total'])||$body['success']!==true||!is_int($body['total'])||$body['total']<0||$body['total']>50000||!is_array($body['data'])||!array_is_list($body['data']))self::fail();
        $seen=[];foreach($body['data']as$row){self::row($row);if(isset($seen[$row['record_key']]))self::fail();$seen[$row['record_key']]=true;}
    }
    public static function getRequest(array$d,array$payload,mixed$id):void
    {
        self::binding($d,$d['apiSource']['detailEndpoint']??[],'get');
        $allowed=['id','record_key','record','context','entityCode','operation','actionId','programId','permissionPrefix','traceability','_runtime','_runtimeEndpoint','runtime',...self::KEYS];
        if(array_diff(array_keys($payload),$allowed)||array_key_exists('context',$payload)&&!in_array($payload['context'],[[],null],true)||array_key_exists('record',$payload)&&!is_array($payload['record']))self::fail(422);
        $key=self::key($id,422);
        foreach([$payload,$payload['record']??[]]as$submitted){
            foreach(['id','record_key']as$name)if(array_key_exists($name,$submitted)&&$submitted[$name]!==$id)self::fail(422);
            foreach(self::KEYS as$name)if(array_key_exists($name,$submitted)&&$submitted[$name]!==$key[$name])self::fail(422);
        }
        if(array_diff(array_keys($payload['record']??[]),['id','record_key',...self::KEYS]))self::fail(422);
    }
    public static function getResponse(array$body,mixed$row,mixed$id):void
    {
        if(!RuntimeProgressAuditSchema::exact($body,['success','data'])||$body['success']!==true)self::fail();
        self::row($row);$key=self::key($id,422);foreach(self::KEYS as$name)if($key[$name]!==$row[$name])self::fail();
    }
}
