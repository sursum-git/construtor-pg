<?php
namespace App\Ciw;
use App\Runtime\RuntimeHttpException;
class CiwSqlTunnelOwnership
{
    public function __construct(private readonly ?string $stateFile=null,private readonly ?\Closure $listenerProbe=null){}
    public function assertOwned():void
    {
        try{
            $file=$this->stateFile??($_SERVER['CIW_SQL_TUNNEL_STATE_FILE']??$_ENV['CIW_SQL_TUNNEL_STATE_FILE']??getenv('CIW_SQL_TUNNEL_STATE_FILE')?:'');
            if(!is_string($file)||$file===''||str_contains($file,'://')||!is_file($file)||filesize($file)>2048)throw new \RuntimeException();
            $s=json_decode(file_get_contents($file),true,8,JSON_THROW_ON_ERROR);
            if(!is_array($s)||!is_int($s['pid']??null)||$s['pid']<1||($s['port']??null)!==19933||($s['host']??null)!=='127.0.0.1'||($s['sshHost']??null)!=='192.168.0.170'||($s['destination']??null)!=='192.168.0.170:1433')throw new \RuntimeException();
            $pid=$this->listenerProbe?($this->listenerProbe)():$this->listenerPid();
            if(!is_int($pid)||$pid!==$s['pid'])throw new \RuntimeException();
        }catch(\Throwable){throw new RuntimeHttpException('CIW_SQL_UNAVAILABLE','Tunel CIW indisponivel.',503);}
    }
    private function listenerPid():?int
    {
        if(PHP_OS_FAMILY!=='Windows')return null;
        $exe=($_SERVER['SystemRoot']??getenv('SystemRoot')?:'C:/Windows').'/System32/netstat.exe';
        $p=proc_open([$exe,'-ano','-p','tcp'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))return null;
        fclose($pipes[0]);$out=stream_get_contents($pipes[1],262144);fclose($pipes[1]);stream_get_contents($pipes[2],8192);fclose($pipes[2]);$exit=proc_close($p);
        if($exit!==0||!is_string($out))return null;
        preg_match_all('/^\s*TCP\s+127\.0\.0\.1:19933\s+0\.0\.0\.0:0\s+\S+\s+(\d+)\s*$/m',$out,$m);
        return count($m[1])===1?(int)$m[1][0]:null;
    }
}
