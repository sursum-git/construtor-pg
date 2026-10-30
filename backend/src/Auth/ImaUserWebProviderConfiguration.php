<?php
namespace App\Auth;
use App\Runtime\RuntimeHttpException;
class ImaUserWebProviderConfiguration
{
    public function __construct(private readonly ?string $connectionFile = null) {}
    public function resolve(): array
    {
        $file = $this->connectionFile ?? ($_SERVER['CIW_AUTH_CONNECTION_FILE'] ?? $_ENV['CIW_AUTH_CONNECTION_FILE'] ?? getenv('CIW_AUTH_CONNECTION_FILE') ?: '');
        try {
            if (!is_string($file) || $file === '' || str_contains($file, '://') || !is_file($file) || !is_readable($file) || filesize($file)>8192) throw new \RuntimeException();
            $text=@file_get_contents($file);
            $cfg=json_decode($text===false?'':$text,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($cfg) || !is_string($cfg['url']??null) || !is_string($cfg['token']??null)) throw new \RuntimeException();
            if (!in_array($cfg['url'],[
                'http://127.0.0.1:19910/med/web/CiwAuthentication',
                'https://192.168.0.111:9911/med/web/CiwAuthentication',
            ],true) || !preg_match('/\A[a-f0-9]{64}\z/i',$cfg['token'])) throw new \RuntimeException();
            $timeout=$cfg['timeoutSeconds']??10;
            if (!is_int($timeout) || $timeout<1 || $timeout>10) throw new \RuntimeException();
            return ['url'=>$cfg['url'],'token'=>$cfg['token'],'timeoutSeconds'=>$timeout];
        } catch (\Throwable) {
            throw new RuntimeHttpException('AUTH_PROVIDER_UNAVAILABLE','Autenticacao IMA indisponivel.',503);
        }
    }
}
