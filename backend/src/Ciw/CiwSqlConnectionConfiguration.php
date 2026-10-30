<?php
namespace App\Ciw;
use App\Runtime\RuntimeHttpException;
class CiwSqlConnectionConfiguration
{
    public function __construct(private readonly ?string $connectionFile=null,private readonly ?CiwSqlTunnelOwnership $ownership=null){}
    public function resolve():array
    {
        try{
            ($this->ownership??new CiwSqlTunnelOwnership())->assertOwned();
            $file=$this->connectionFile??($_SERVER['CIW_SQL_CONNECTION_FILE']??$_ENV['CIW_SQL_CONNECTION_FILE']??getenv('CIW_SQL_CONNECTION_FILE')?:'');
            if(!is_string($file)||$file===''||str_contains($file,'://')||!is_file($file)||!is_readable($file))throw new \RuntimeException();
            clearstatcache(true,$file);if(filesize($file)>8192)throw new \RuntimeException();
            $c=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($c)||array_diff(array_keys($c),['host','port','database','username','password','timeoutSeconds']))throw new \RuntimeException();
            if(($c['host']??null)!=='127.0.0.1'||($c['port']??null)!==19933||($c['database']??null)!=='ticontrole')throw new \RuntimeException();
            if(!is_string($c['username']??null)||!preg_match('/\A[A-Za-z0-9_.@\\\\-]{1,160}\z/',$c['username'])||!is_string($c['password']??null)||$c['password']===''||strlen($c['password'])>1024)throw new \RuntimeException();
            $c['timeoutSeconds']??=8;if(!is_int($c['timeoutSeconds'])||$c['timeoutSeconds']<1||$c['timeoutSeconds']>10)throw new \RuntimeException();
            return $c;
        }catch(\Throwable){throw new RuntimeHttpException('CIW_SQL_UNAVAILABLE','Permissoes CIW indisponiveis.',503);}
    }
}
