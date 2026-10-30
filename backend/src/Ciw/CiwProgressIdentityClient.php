<?php
namespace App\Ciw;
use App\Entity\RuntimeUserSession;
use App\Runtime\RuntimeHttpException;
class CiwProgressIdentityClient implements CiwProgressIdentityClientInterface
{
    public function __construct(private readonly ?string $connectionFile=null,private readonly ?\Closure $sender=null){}
    public function assertActive(RuntimeUserSession $session):void
    {
        if($session->getStatus()!=='active'||($session->getSessionProperties()['authentication']['provider']??null)!=='ima-user-web'||$session->getUserId()===''||$session->getSessionId()==='')throw new RuntimeHttpException('CIW_IDENTITY_REQUIRED','Identidade obrigatoria.',401);
        try{
            $file=$this->connectionFile??($_SERVER['CIW_IDENTITY_CONNECTION_FILE']??$_ENV['CIW_IDENTITY_CONNECTION_FILE']??getenv('CIW_IDENTITY_CONNECTION_FILE')?:'');
            if(!is_string($file)||$file===''||str_contains($file,'://')||!is_file($file)||filesize($file)>8192)throw new \RuntimeException();
            $c=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($c)||!in_array($c['url']??null,['http://127.0.0.1:19910/med/web/SursumPrograms/ciw-identity','https://192.168.0.111:9911/med/web/SursumPrograms/ciw-identity'],true)||!is_string($c['token']??null)||!preg_match('/\A[a-f0-9]{64}\z/i',$c['token']))throw new \RuntimeException();
            $timeout=$c['timeoutSeconds']??8;if(!is_int($timeout)||$timeout<1||$timeout>10)throw new \RuntimeException();
            $headers=['X-CiwAccess-Token'=>$c['token'],'X-Sursum-User'=>$session->getUserId(),'X-Session-Id'=>$session->getSessionId(),'X-Sursum-Auth-Source'=>'ima-user-web'];
            foreach($headers as $v)if(preg_match('/[\x00-\x1f\x7f]/',$v))throw new \RuntimeException();
            $r=$this->sender?($this->sender)($c['url'],$headers,['operation'=>'identify'],$timeout):$this->send($c['url'],$headers,$timeout);
        }catch(\Throwable){throw new RuntimeHttpException('CIW_IDENTITY_UNAVAILABLE','Identidade CIW indisponivel.',503);}
        $b=$r['body']??null;
        if(is_array($b)&&($b['success']??null)===false){
            $code=$b['error']['code']??null;$status=$b['error']['status']??null;
            if(in_array($code,['CIW_IDENTITY_NOT_LINKED','CIW_IDENTITY_INACTIVE','CIW_IDENTITY_AMBIGUOUS','CIW_AUTH_SOURCE_DENIED','CIW_IDENTITY_REQUIRED'],true))throw new RuntimeHttpException($code,'Identidade CIW recusada.',in_array($status,[401,403,409],true)?$status:403);
        }
        if(($r['status']??null)!==200||!is_array($b)||($b['success']??null)!==true||!is_array($b['data']??null)||count($b['data'])!==1||!is_string($b['data'][0]['login']??null)||strcasecmp($b['data'][0]['login'],$session->getUserId())!==0)throw new RuntimeHttpException('CIW_IDENTITY_UNAVAILABLE','Identidade CIW indisponivel.',503);
    }
    private function send(string $url,array $headers,int $timeout):array
    {
        $ch=curl_init($url);if($ch===false)throw new \RuntimeException();
        try{
            $h=['Content-Type: application/json'];foreach($headers as $k=>$v)$h[]=$k.': '.$v;
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'{"operation":"identify"}',CURLOPT_HTTPHEADER=>$h,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>$timeout,CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
            $raw=curl_exec($ch);if(!is_string($raw)||strlen($raw)>65536)throw new \RuntimeException();
            return ['status'=>curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'body'=>json_decode($raw,true,16,JSON_THROW_ON_ERROR)];
        }finally{curl_close($ch);}
    }
}
