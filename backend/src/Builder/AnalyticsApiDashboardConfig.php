<?php
namespace App\Builder;
use App\Entity\BuilderEntity;
use App\Runtime\RuntimeHttpException;

/** Closed reusable dashboard configuration; no script, expression or template properties. */
final class AnalyticsApiDashboardConfig
{
    private static function invalid(): never { throw new RuntimeHttpException('ANALYTICS_DASHBOARD_INVALID','Configuracao do painel analytics invalida.',422); }
    private static function keys(array $value,array $allowed):void {if(array_diff(array_keys($value),$allowed))self::invalid();}
    private static function code(mixed $value):string {if(!is_string($value)||!preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,119}$/D',$value)||in_array($value,['__proto__','constructor','prototype'],true))self::invalid();return $value;}
    public static function normalize(array $config,callable $findEntity):array
    {
        self::keys($config,['datasets','dashboard','navigation','navigationHosts']);
        if(!is_array($config['datasets']??null)||!array_is_list($config['datasets'])||!count($config['datasets'])||count($config['datasets'])>10)self::invalid();
        $datasets=[];$fieldsByDataset=[];
        foreach($config['datasets'] as $dataset){
            if(!is_array($dataset))self::invalid();
            self::keys($dataset,['id','title','source','fields','limit','defaultSort','executionMode']);
            $id=self::code($dataset['id']??null);
            if(isset($datasets[$id])||!is_array($dataset['source']??null))self::invalid();
            self::keys($dataset['source'],['type','entityCode']);
            $entityCode=self::code($dataset['source']['entityCode']??null);
            $entity=$findEntity($entityCode);
            if(($dataset['source']['type']??null)!=='api'||!$entity instanceof BuilderEntity||$entity->getEntityType()!=='api'||empty($entity->getMetadata()['apiSource']['listEndpoint']['url']))self::invalid();
            $registered=[];foreach($entity->getFields() as $field)$registered[$field->getCode()]=$field;
            $fields=[];
            if(!is_array($dataset['fields']??null)||!array_is_list($dataset['fields'])||!count($dataset['fields']))self::invalid();
            foreach($dataset['fields'] as $spec){
                $spec=is_string($spec)?['field'=>$spec]:$spec;
                if(!is_array($spec))self::invalid();self::keys($spec,['id','field','label','type','format']);
                $name=self::code($spec['field']??null);if(!isset($registered[$name])||isset($fields[$name]))self::invalid();
                $field=$registered[$name];
                if(($spec['id']??$name)!==$name||($spec['type']??$field->getDataType())!==$field->getDataType())self::invalid();
                $format=$spec['format']??($field->getDataType()==='integer'?'n0':null);
                if($format!==null&&!in_array($format,['n0','n2','c2','percent0','percent2'],true))self::invalid();
                $fields[$name]=['id'=>$name,'field'=>$name,'label'=>self::label($spec['label']??$field->getLabel()),'type'=>$field->getDataType(),'format'=>$format];
                if($format===null)unset($fields[$name]['format']);
            }
            $limit=$dataset['limit']??1000;if(!is_int($limit)||$limit<1||$limit>5000)self::invalid();
            $sort=$dataset['defaultSort']??[];if(!is_array($sort)||!array_is_list($sort))self::invalid();
            foreach($sort as $order){if(!is_array($order))self::invalid();self::keys($order,['field','dir']);if(!isset($fields[$order['field']??''])||!in_array($order['dir']??null,['asc','desc'],true))self::invalid();}
            $datasets[$id]=['id'=>$id,'title'=>self::label($dataset['title']??$id),'source'=>['type'=>'api','entityCode'=>$entityCode],'fields'=>array_values($fields),'limit'=>$limit,'defaultSort'=>$sort,'executionMode'=>'live'];
            $fieldsByDataset[$id]=$fields;
        }
        $hosts=$config['navigationHosts']??[];$navigation=$config['navigation']??[];
        if(!is_array($hosts)||!array_is_list($hosts)||count($hosts)>10||!is_array($navigation)||count($navigation)>50)self::invalid();
        foreach($hosts as $host)if(!is_string($host)||!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D',$host))self::invalid();
        $safeNavigation=[];
        foreach($navigation as $id=>$link){
            self::code($id);if(!is_array($link))self::invalid();self::keys($link,['label','url']);
            $url=$link['url']??null;if(!is_string($url)||preg_match('/[\x00-\x20\\\\]/',$url))self::invalid();
            $parts=parse_url($url);
            if(!is_array($parts)||($parts['scheme']??'')!=='https'||!in_array($parts['host']??null,$hosts,true)||array_intersect(array_keys($parts),['user','pass','query','fragment','port']))self::invalid();
            $safeNavigation[$id]=['label'=>self::label($link['label']??$id),'url'=>$url];
        }
        $dashboard=$config['dashboard']??null;if(!is_array($dashboard))self::invalid();
        self::keys($dashboard,['id','type','title','datasetId','tiles']);
        if(($dashboard['type']??'dashboard')!=='dashboard')self::invalid();
        $default=$dashboard['datasetId']??array_key_first($datasets);if(!isset($datasets[$default]))self::invalid();
        $ids=[];
        $tiles=self::tiles($dashboard['tiles']??null,$default,$fieldsByDataset,$safeNavigation,0,$ids);
        return ['datasets'=>array_values($datasets),'dashboard'=>['id'=>self::code($dashboard['id']??'dashboard'),'type'=>'dashboard','title'=>self::label($dashboard['title']??'Painel'),'datasetId'=>$default,'tiles'=>$tiles],
            'navigation'=>$safeNavigation,'navigationHosts'=>$hosts];
    }
    private static function label(mixed $value):string {if(!is_string($value)||mb_strlen($value)>200)self::invalid();return $value;}
    private static function tiles(mixed $tiles,string $default,array $fields,array $navigation,int $depth,array &$ids):array
    {
        if($depth>2||!is_array($tiles)||!array_is_list($tiles)||!count($tiles)||count($tiles)>50)self::invalid();
        $result=[];
        foreach($tiles as $tile){
            if(!is_array($tile))self::invalid();
            self::keys($tile,['id','type','title','datasetId','valueField','labelField','labelCase','format','items','children','collapsed','navigationId']);
            $id=self::code($tile['id']??null);if(isset($ids[$id]))self::invalid();$ids[$id]=true;
            $type=$tile['type']??'';if(!in_array($type,['kpi','progress','list','links','group'],true))self::invalid();
            $dataset=$tile['datasetId']??$default;if(!is_string($dataset)||!isset($fields[$dataset]))self::invalid();
            $safe=['id'=>$id,'type'=>$type,'title'=>self::label($tile['title']??''),'datasetId'=>$dataset];
            if(in_array($type,['kpi','progress','list'],true)){
                $name=self::code($tile['valueField']??null);$f=$fields[$dataset][$name]??null;
                if(!$f||!in_array($f['type'],['integer','decimal','currency','number'],true))self::invalid();$safe['valueField']=$name;
                $safe['format']=$tile['format']??($type==='progress'?'percent0':($f['format']??'n2'));
                if(!in_array($safe['format'],['n0','n2','c2','percent0','percent2'],true))self::invalid();
            }
            if($type==='list'){$name=self::code($tile['labelField']??null);if(!isset($fields[$dataset][$name])||$fields[$dataset][$name]['type']!=='string')self::invalid();$safe['labelField']=$name;}
            if(isset($tile['labelCase'])){if($type!=='list'||!in_array($tile['labelCase'],['none','title'],true))self::invalid();$safe['labelCase']=$tile['labelCase'];}
            if($type==='group'){
                if(isset($tile['collapsed'])&&!is_bool($tile['collapsed']))self::invalid();
                $safe['collapsed']=$tile['collapsed']??false;$safe['children']=self::tiles($tile['children']??null,$dataset,$fields,$navigation,$depth+1,$ids);
            }
            if(isset($tile['navigationId'])){if(!isset($navigation[$tile['navigationId']]))self::invalid();$safe['navigationId']=$tile['navigationId'];}
            if($type==='links'){
                $items=$tile['items']??null;if(!is_array($items)||!array_is_list($items)||count($items)>50)self::invalid();$safe['items']=[];
                foreach($items as $item){if(!is_array($item))self::invalid();self::keys($item,['navigationId']);if(!isset($navigation[$item['navigationId']??'']))self::invalid();$safe['items'][]=$item;}
            }
            $result[]=$safe;
        }
        return $result;
    }
}
