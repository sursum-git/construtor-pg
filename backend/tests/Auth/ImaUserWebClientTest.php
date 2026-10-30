<?php
namespace App\Tests\Auth;
use App\Auth\ImaUserWebClient;
use App\Auth\ImaUserWebProviderConfiguration;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
class ImaUserWebClientTest extends TestCase
{
    private static $process;
    private static string $base;
    private string $configFile;
    public static function setUpBeforeClass(): void
    {
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        $addr=stream_socket_get_name($socket,false);fclose($socket);
        self::$base='http://'.$addr;
        self::$process=proc_open([PHP_BINARY,'-S',$addr,__DIR__.'/fixtures/ima-user-web.php'],[['pipe','r'],['file','NUL','w'],['file','NUL','w']],$pipes);
        for($n=0;$n<50;$n++){ $s=@stream_socket_client('tcp://'.$addr,$errno,$error,.05);if($s){fclose($s);return;}usleep(20000);}
        throw new \RuntimeException('Fixture nao iniciou');
    }
    public static function tearDownAfterClass(): void
    {if(is_resource(self::$process)){proc_terminate(self::$process);proc_close(self::$process);}}
    protected function setUp(): void
    {
        $this->configFile=tempnam(sys_get_temp_dir(),'ciwauth');
        file_put_contents($this->configFile,json_encode(['url'=>'http://127.0.0.1:19910/med/web/CiwAuthentication','token'=>str_repeat('a',64),'timeoutSeconds'=>1]));
    }
    protected function tearDown(): void {unlink($this->configFile);}
    private function client(string $mode): ImaUserWebClient
    {
        return new class(new ImaUserWebProviderConfiguration($this->configFile),self::$base.'/?mode='.$mode) extends ImaUserWebClient {
            public function __construct($config,private string $fixtureUrl){parent::__construct($config);}
            protected function perform(string $url,array $headers,string $body,int $timeout): array
            {return parent::perform($this->fixtureUrl,$headers,$body,$timeout);}
        };
    }
    public function testRealHttpPreservesCredentialBytesAndReturnsMinimalIdentity(): void
    {self::assertSame('CIW_TEST',$this->client('valid')->authenticate('ciw_test',"A'ç "));}
    public function testMalformedRedirectLargeOrErrorReplyNeverLeaksContent(): void
    {
        foreach(['redirect','huge','secret-error','malformed'] as $mode){
            try {$this->client($mode)->authenticate('ciw_test',"A'ç ");self::fail('Must deny '.$mode);}
            catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());self::assertStringNotContainsString('SYNTHETIC_MARKER_MUST_NOT_LEAK',$e->getMessage());}
        }
    }
    public function testDeniedCredentialHasGenericError(): void
    {
        try {$this->client('denied')->authenticate('ciw_test',"A'ç ");self::fail('Must deny');}
        catch(RuntimeHttpException $e){self::assertSame('INVALID_CREDENTIALS',$e->getErrorCode());self::assertSame(401,$e->getStatusCode());}
    }
    public function testTimeoutIsSanitized(): void
    {
        $start=microtime(true);
        try {$this->client('timeout')->authenticate('ciw_test',"A'ç ");self::fail('Must timeout');}
        catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());self::assertLessThan(3,microtime(true)-$start);}
    }
}
