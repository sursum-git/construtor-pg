<?php
namespace App\Runtime;

/** Scalar parsing shared by closed Progress audit contracts. This class grants no resources or scopes. */
final class RuntimeProgressAuditSchema
{
    public static function exact(mixed $row,array $keys): bool
    { return is_array($row) && !array_is_list($row) && !array_diff(array_keys($row),$keys) && !array_diff($keys,array_keys($row)); }
    public static function id(mixed $v): bool { return is_int($v) && $v>0 && $v<=2147483647; }
    public static function key(mixed $v): bool { return is_string($v) && preg_match('/\Ap_[A-F0-9]{64}\z/D',$v)===1; }
    public static function date(mixed $v): bool
    { return is_string($v) && preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/',$v,$p)===1 && checkdate((int)$p[2],(int)$p[3],(int)$p[1]); }
    public static function time(mixed $v): bool
    { return is_string($v) && preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]{3})?\z/',$v,$p)===1 && self::date($p[1]) && (int)$p[2]<24 && (int)$p[3]<60 && (int)$p[4]<60; }
    public static function row(mixed $row,array $schema): bool
    {
        if (!self::exact($row,array_keys($schema))) { return false; }
        foreach ($schema as $name=>$rule) {
            $rule=is_string($rule)?['type'=>$rule]:$rule;$type=$rule['type'];$v=$row[$name];
            if ($v===null) { if ($rule['nullable']??in_array($type,['date','datetime'],true)) { continue; } return false; }
            $valid=match($type) {
                'id'=>self::id($v),'key'=>self::key($v),'stamp'=>is_string($v) && preg_match('/\A[A-F0-9]{64}\z/D',$v)===1,
                'titles'=>is_array($v) && array_is_list($v),
                'integer'=>is_int($v) && $v>=-2147483648 && $v<=2147483647,
                'decimal'=>(is_int($v)||is_float($v)) && is_finite((float)$v),
                'boolean'=>is_bool($v),'date'=>self::date($v),'datetime'=>self::time($v),
                'string'=>is_string($v) && mb_strlen($v)<=($rule['maxLength']??32000),
                default=>false,
            };
            if (!$valid) { return false; }
        }
        return true;
    }
    public static function same(array $a,array $b): bool
    {
        if (array_diff_key($a,$b) || array_diff_key($b,$a)) { return false; }
        foreach ($a as $key=>$value) { if ($value!==$b[$key]) { return false; } }
        return true;
    }
    public static function ciwBinding(array $d,array $e,string $entity,string $primaryKey,string $resource,string $operation,array $operations): bool
    {
        $url=parse_url((string)($e['url']??''));
        return ($d['entityCode']??null)===$entity && ($d['primaryKey']??null)===$primaryKey
            && ($d['apiBinding']['sourceCode']??null)==='motor_progress_ciw_cadastros' && ($d['apiSource']['responseContract']??null)==='sursum-programs'
            && in_array($operation,$operations,true) && ($e['method']??null)==='POST' && self::exact($e['bodyTemplate']??null,['resource','operation'])
            && $e['bodyTemplate']['operation']===$operation && $e['bodyTemplate']['resource']===$resource
            && is_array($url) && !array_intersect(['user','pass','query','fragment'],array_keys($url))
            && in_array($url['path']??null,['/SursumPrograms/ciw-cadastros','/med/web/SursumPrograms/ciw-cadastros'],true)
            && (($url['scheme']??null)==='http' && ($url['host']??null)==='127.0.0.1' && ($url['port']??null)===19910
                || ($url['scheme']??null)==='https' && ($url['host']??null)==='192.168.0.111' && ($url['port']??null)===9911);
    }
}
