<?php
namespace App\Ciw;
use App\Runtime\RuntimeHttpException;
class CiwColoracoesPolicy
{
    private const FLAGS=['read'=>null,'get'=>null,'create'=>'insert','update'=>'update','delete'=>'delete','export'=>'export','print'=>'print'];
    public function applicationFor(string $action): string
    {
        if(!array_key_exists($action,self::FLAGS))throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);
        return in_array($action,['read','export','print'],true)?'grid_pub_coloracoes':'form_pub_coloracoes';
    }
    public function allows(string $action,array $grants): bool
    {
        $this->applicationFor($action);$flag=self::FLAGS[$action];$access=false;$allowed=$flag===null;
        foreach($grants as $grant){$access=$access||($grant['access']??null)==='Y';if($flag!==null)$allowed=$allowed||($grant[$flag]??null)==='Y';}
        return $access&&$allowed;
    }
}
