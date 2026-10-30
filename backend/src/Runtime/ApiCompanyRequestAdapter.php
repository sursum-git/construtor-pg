<?php
namespace App\Runtime;

use App\Builder\ApiCompanyContextValidator;

class ApiCompanyRequestAdapter
{
    public function apply(ApiCompanyContext $context,array $mapping,array $endpoint,array $request):array
    {
        ApiCompanyContextValidator::validateSourceConfig(['version'=>1,'adapters'=>['runtime'=>$mapping]]);
        $values=$context->trustedValues();
        $request+=['headers'=>[],'query'=>[],'body'=>null];
        if(!is_array($request['headers'])||!is_array($request['query']))$this->conflict();
        foreach($mapping as $location=>$fields){
            foreach($fields as $field=>$valueKey){
                $value=$values[$valueKey]??null;
                if(!is_int($value)||$value<1)throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Codigo empresarial exigido pelo adaptador nao esta configurado.',422);
                if($location==='body'){
                    if(is_array($endpoint['bodyTemplate']??null))$this->inspect($endpoint['bodyTemplate'],$field,$value);
                    if(!in_array(strtoupper($endpoint['method']??'GET'),['POST','PUT','PATCH','DELETE'],true))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Contexto em corpo exige operacao com corpo JSON.',422);
                    if($request['body']===null)$request['body']=[];
                    if(!is_array($request['body'])||array_is_list($request['body'])&&$request['body']!==[])$this->conflict();
                    $this->inspect($request['body'],$field,$value);
                    $request['body'][$field]=$value;
                }elseif($location==='query'){
                    foreach(explode('&',(string)(parse_url((string)($endpoint['url']??''),PHP_URL_QUERY)??''))as $part){
                        if(urldecode(explode('=',$part,2)[0])===$field)$this->conflict();
                    }
                    if(array_key_exists($field,$request['query'])&&(string)$request['query'][$field]!== (string)$value)$this->conflict();
                    $request['query'][$field]=$value;
                }else{
                    foreach($request['headers']as $name=>$existing){
                        if(strtolower($name)===strtolower($field)){
                            if((string)$existing!==(string)$value)$this->conflict();
                            unset($request['headers'][$name]);
                        }
                    }
                    $request['headers'][$field]=(string)$value;
                }
            }
        }
        return $request;
    }

    public function assertNoConflictInInput(ApiCompanyContext $context,array $payload):void
    {
        foreach($context->mapping['body']??[]as $field=>$valueKey)$this->inspect($payload,$field,$context->trustedValues()[$valueKey]);
        foreach($context->mapping['query']??[]as $field=>$valueKey)$this->inspect($payload,$field,$context->trustedValues()[$valueKey]);
    }
    private function inspect(array $node,string $field,int $expected,int $depth=0):void
    {
        if($depth>32)$this->conflict();
        if(array_key_exists($field,$node)&&$node[$field]!==$expected)$this->conflict();
        if(($node['field']??null)===$field&&(($node['operator']??'eq')!=='eq'||($node['value']??null)!==$expected))$this->conflict();
        foreach($node as $child)if(is_array($child))$this->inspect($child,$field,$expected,$depth+1);
    }
    private function conflict():never{throw new RuntimeHttpException('API_COMPANY_CONTEXT_CONFLICT','A empresa informada diverge da selecao autorizada.',409);}
}
