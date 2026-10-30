<?php
namespace App\Tests\Runtime;
use App\Runtime\RuntimeExternalJsonClient;
use PHPUnit\Framework\TestCase;

final class RuntimeAnalyticsReadBatchTest extends TestCase
{
    private static mixed $server;
    private static array $pipes=[];
    private static string $url;
    public static function setUpBeforeClass():void {
        $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);self::$url='http://'.$address;
        self::$server=proc_open([PHP_BINARY,'-S',$address,__DIR__.'/fixtures/analytics-read-batch.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],self::$pipes);
        $deadline=microtime(true)+5;do{$connection=@stream_socket_client('tcp://'.$address,$errno,$message,.1);if($connection){fclose($connection);return;}usleep(10000);}while(microtime(true)<$deadline);self::fail('Fixture HTTP indisponivel');
    }
    public static function tearDownAfterClass():void {if(is_resource(self::$server)){proc_terminate(self::$server);foreach(self::$pipes as $p)if(is_resource($p))fclose($p);proc_close(self::$server);}}
    private function read(RuntimeExternalJsonClient $client,array $headers=[],mixed $payload=null):array {return $client->request(self::$url,'POST',$headers,$payload,5)['body'];}
    public function testIdenticalReadsShareOnlyOneResponseInsideTheBatch():void {
        $client=new RuntimeExternalJsonClient();$responses=$client->withReadBatch(fn()=>[$this->read($client),$this->read($client)]);
        self::assertSame($responses[0]['nonce'],$responses[1]['nonce']);
        self::assertNotSame($responses[0]['nonce'],$this->read($client)['nonce']);
        self::assertNotSame($this->read($client)['nonce'],$this->read($client)['nonce']);
    }
    public function testHeadersAndBodiesNeverShareResponses():void {
        $client=new RuntimeExternalJsonClient();$responses=$client->withReadBatch(fn()=>[$this->read($client,['X-Sursum-User'=>'a']),$this->read($client,['X-Sursum-User'=>'b']),$this->read($client,['X-Sursum-User'=>'a'],['resource'=>'other'])]);
        self::assertCount(3,array_unique(array_column($responses,'nonce')));
    }
    public function testFailureDiscardsTheWholeScope():void {
        $client=new RuntimeExternalJsonClient();$before=null;
        try{$client->withReadBatch(function()use($client,&$before){$before=$this->read($client);throw new \RuntimeException('fixture');});}catch(\RuntimeException){}
        self::assertNotSame($before['nonce'],$client->withReadBatch(fn()=>$this->read($client))['nonce']);
    }
    public function testAdditionalLookupReadsContinueAfterTheSharingBudget():void {
        $client=new RuntimeExternalJsonClient();$responses=$client->withReadBatch(function()use($client){$rows=[];for($i=0;$i<12;$i++)$rows[]=$this->read($client,[],['lookup'=>$i]);return $rows;});
        self::assertCount(12,array_unique(array_column($responses,'nonce')));
    }
}
