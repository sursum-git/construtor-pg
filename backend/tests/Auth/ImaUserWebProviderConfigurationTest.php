<?php
namespace App\Tests\Auth;
use App\Auth\ImaUserWebProviderConfiguration;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
class ImaUserWebProviderConfigurationTest extends TestCase
{
    public function testPrivateConfigurationStrictlyPinsEndpoint(): void
    {
        $file=tempnam(sys_get_temp_dir(),'ciwauth');
        try {
            foreach ([
                'http://127.0.0.1:19910/med/web/CiwAuthentication'=>true,
                'https://192.168.0.111:9911/med/web/CiwAuthentication'=>true,
                'http://192.168.0.111:9910/med/web/CiwAuthentication'=>false,
                'http://127.0.0.1:19910/med/web/CiwAuthentication?other=1'=>false,
                'https://evil.test/med/web/CiwAuthentication'=>false,
            ] as $url=>$ok) {
                file_put_contents($file,json_encode(['url'=>$url,'token'=>str_repeat('a',64),'timeoutSeconds'=>10]));
                try {
                    $r=(new ImaUserWebProviderConfiguration($file))->resolve();
                    self::assertTrue($ok,'Unexpected accepted endpoint'); self::assertSame($url,$r['url']);
                } catch(RuntimeHttpException $e) {self::assertFalse($ok); self::assertStringNotContainsString(str_repeat('a',64),$e->getMessage());}
            }
        } finally {unlink($file);}
    }
    public function testMalformedOrMissingPrivateConfigFailsClosed(): void
    {
        $file=tempnam(sys_get_temp_dir(),'ciwauth');
        try {
            file_put_contents($file,'{"token":"SYNTHETIC_SECRET"');
            $this->expectException(RuntimeHttpException::class);
            (new ImaUserWebProviderConfiguration($file))->resolve();
        } finally {unlink($file);}
    }
}
