<?php
namespace App\Ciw;
use App\Runtime\RuntimeHttpException;
class PdoCiwSqlGrantReader implements CiwSqlGrantReaderInterface
{
    public function __construct(private readonly CiwSqlConnectionConfiguration $configuration,private readonly ?\Closure $connector=null){}
    public function read(string $subject,string $application):array
    {
        if(!preg_match('/\A[A-Za-z0-9_.@-]{1,160}\z/',$subject)||!in_array($application,['grid_pub_coloracoes','form_pub_coloracoes'],true))throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);
        try{
            if($this->connector){$pdo=($this->connector)();}
            else{
                $c=$this->configuration->resolve();
                $pdo=new \PDO('odbc:Driver={ODBC Driver 17 for SQL Server};Server=127.0.0.1,19933;Database=ticontrole;Encrypt=no;', $c['username'],$c['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>$c['timeoutSeconds']]);
                unset($c);
            }
            $s=$pdo->prepare('SELECT active FROM dbo.s_users WHERE login = ?');$s->execute([$subject]);$users=$s->fetchAll(\PDO::FETCH_ASSOC);
            if(count($users)>1)throw new \RuntimeException();
            if(count($users)!==1||($users[0]['active']??null)!=='Y')return ['active'=>false,'grants'=>[]];
            $s=$pdo->prepare('SELECT ga.priv_access AS [access],ga.priv_insert AS [insert],ga.priv_update AS [update],ga.priv_delete AS [delete],ga.priv_export AS [export],ga.priv_print AS [print] FROM dbo.programas p JOIN dbo.modulos m ON m.cod_modulo = p.cod_modulo JOIN dbo.s_groups_apps ga ON ga.cod_programa = p.cod_programa WHERE m.cod_sistema = 18 AND LOWER(p.codigo) = ? AND EXISTS (SELECT 1 FROM dbo.s_users_groups ug WHERE ug.group_id = ga.group_id AND ug.login = ?)');
            $s->execute([$application,$subject]);return ['active'=>true,'grants'=>$s->fetchAll(\PDO::FETCH_ASSOC)];
        }catch(\Throwable){throw new RuntimeHttpException('CIW_SQL_UNAVAILABLE','Permissoes CIW indisponiveis.',503);}
    }
}
