<?php
namespace App\Builder;

use App\Runtime\RuntimeHttpException;

/** Closed metadata contract; values are corporate identifiers, never credentials or code. */
class ApiCompanyContextValidator
{
    private static function invalid():never
    {throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Configuracao de empresa da API invalida.',422);}
    public static function key(mixed $value):string
    {
        if(!is_string($value)||!preg_match('/^[a-z0-9_-]{1,64}$/D',$value))self::invalid();
        return $value;
    }
    private static function keys(array $value,array $allowed,array $required=[]):void
    {
        if(array_diff(array_keys($value),$allowed)||array_diff($required,array_keys($value)))self::invalid();
    }
    public static function validateBinding(array $binding):array
    {
        $keys=['key','ownerTenantId','moduleId','apiSourceCode','externalCompanyId','ems2Code','ems5Code','revision','enabled'];
        self::keys($binding,$keys,$keys);
        foreach(['key','moduleId','apiSourceCode']as $key)self::key($binding[$key]);
        if(!is_string($binding['ownerTenantId'])||!preg_match('/^[A-Za-z0-9_.:@ -]{1,80}$/D',$binding['ownerTenantId']))self::invalid();
        foreach(['externalCompanyId'=>1,'ems2Code'=>0,'ems5Code'=>0,'revision'=>1]as $key=>$minimum){
            if(!is_int($binding[$key])||$binding[$key]<$minimum||$binding[$key]>2147483647)self::invalid();
        }
        if(!is_bool($binding['enabled']))self::invalid();
        return $binding;
    }
    public static function validateEntityConfig(mixed $config,string $entityType):array
    {
        if(!is_array($config))self::invalid();
        if(!$config)return [];
        if($entityType!=='api')self::invalid();
        // One transport mapping applies to every API call. Table/database rules belong to the API.
        if(array_key_exists('adapter',$config)){
            self::keys($config,['version','moduleId','adapter'],['version','moduleId','adapter']);
            if($config['version']!==1)self::invalid();
            self::key($config['moduleId']);self::key($config['adapter']);
            return $config;
        }
        // Preserve published version-one contracts without silently changing their behavior.
        self::keys($config,['version','moduleId','operations'],['version','moduleId','operations']);
        if($config['version']!==1||!is_array($config['operations'])||!$config['operations']||count($config['operations'])>64)self::invalid();
        self::key($config['moduleId']);
        foreach($config['operations']as $operation=>$entry){
            self::key($operation);if(!is_array($entry))self::invalid();
            self::keys($entry,['mode','adapter'],['mode']);
            if(!in_array($entry['mode'],['shared','company','record'],true))self::invalid();
            if($entry['mode']==='shared'){if(array_key_exists('adapter',$entry))self::invalid();}
            else self::key($entry['adapter']??null);
        }
        return $config;
    }
    public static function operationEntry(array $config,string $operation):?array
    {
        if(isset($config['adapter']))return ['mode'=>'company','adapter'=>$config['adapter']];
        return $config['operations'][$operation]??null;
    }
    public static function transportEntries(array $config):array
    {
        return isset($config['adapter'])?[self::operationEntry($config,'')]:array_values($config['operations']??[]);
    }
    public static function validateSourceConfig(mixed $config):array
    {
        if(!is_array($config))self::invalid();
        if(!$config)return [];
        self::keys($config,['version','adapters'],['version','adapters']);
        if($config['version']!==1||!is_array($config['adapters'])||!$config['adapters']||count($config['adapters'])>32)self::invalid();
        foreach($config['adapters']as $id=>$mapping){
            self::key($id);if(!is_array($mapping))self::invalid();self::keys($mapping,['body','query','headers']);
            $count=0;
            foreach($mapping as $location=>$fields){
                if(!is_array($fields)||count($fields)>8)self::invalid();
                foreach($fields as $field=>$value){
                    if(!is_string($field)||!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D',$field)
                        ||preg_match('/auth|token|password|senha|secret|cookie/i',$field)
                        ||in_array(strtolower($field),['host','content-type','content-length','connection','transfer-encoding'],true)
                        ||!in_array($value,['externalCompanyId','ems2Code','ems5Code'],true))self::invalid();
                    ++$count;
                }
            }
            if($count===0)self::invalid();
        }
        return $config;
    }
}
