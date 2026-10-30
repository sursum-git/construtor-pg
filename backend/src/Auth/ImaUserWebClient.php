<?php
namespace App\Auth;
use App\Runtime\RuntimeHttpException;
class ImaUserWebClient implements ImaUserWebClientInterface
{
    public function __construct(private readonly ImaUserWebProviderConfiguration $configuration) {}
    public function authenticate(string $username,string $password): string
    {
        try {
            $cfg=$this->configuration->resolve();
            $body=json_encode(['operation'=>'authenticate','username'=>$username,'password'=>$password],JSON_THROW_ON_ERROR);
            $result=$this->perform($cfg['url'],['Content-Type: application/json','Accept: application/json','X-Ciw-Auth-Token: '.$cfg['token']],$body,$cfg['timeoutSeconds']);
            if ($result['status']===401) throw new RuntimeHttpException('INVALID_CREDENTIALS','Usuario ou senha invalidos.',401);
            if ($result['status']!==200) $this->unavailable();
            $data=json_decode($result['body'],true,8,JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['success']??null)!==true || !is_array($data['data']??null)
                || !array_is_list($data['data']) || count($data['data'])!==1
                || !is_array($data['data'][0]) || array_keys($data['data'][0])!==['login']) $this->unavailable();
            $login=$data['data'][0]['login'];
            if (!is_string($login) || !mb_check_encoding($login,'UTF-8') || trim($login)==='' || mb_strlen($login)>160
                || preg_match('/[\x00-\x1f\x7f]/u',$login)) $this->unavailable();
            return $login;
        } catch (RuntimeHttpException $e) {
            throw $e;
        } catch (\Throwable) {
            $this->unavailable();
        } finally {
            unset($password,$body,$cfg,$result,$data);
        }
    }
    /** Internal transport; no profiler, verbose output, redirects or payload logging. */
    protected function perform(string $url,array $headers,string $body,int $timeout): array
    {
        if (!extension_loaded('curl')) $this->unavailable();
        $handle=curl_init($url);
        if ($handle===false) $this->unavailable();
        $response='';
        try {
            curl_setopt_array($handle,[
                CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
                CURLOPT_CONNECTTIMEOUT=>min(3,$timeout),CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_VERBOSE=>false,CURLOPT_HEADER=>false,
                CURLOPT_WRITEFUNCTION=>static function($h,string $chunk) use (&$response): int {
                    if (strlen($response)+strlen($chunk)>16384) return 0;
                    $response.=$chunk;return strlen($chunk);
                },
            ]);
            if (curl_exec($handle)===false) $this->unavailable();
            return ['status'=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body'=>$response];
        } finally {
            curl_close($handle);unset($body,$headers,$response);
        }
    }
    private function unavailable(): never
    { throw new RuntimeHttpException('AUTH_PROVIDER_UNAVAILABLE','Autenticacao IMA indisponivel.',503); }
}
