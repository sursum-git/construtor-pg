<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderApiProcessOptInTest extends TestCase
{
    private function invoke(string $method, mixed ...$args): mixed {
        $class = new \ReflectionClass(ProgramBuilderService::class);
        return $class->getMethod($method)->invoke($class->newInstanceWithoutConstructor(), ...$args);
    }
    private function operation(mixed $fields): array {
        return ['code'=>'read', 'name'=>'Consultar', 'type'=>'list', 'path'=>'https://progress.example/container-itens', 'method'=>'POST', 'itemsPath'=>'data', 'requestContextFields'=>$fields];
    }
    public function testProcessOptInRequiresCustomPostAndSurvivesBinding(): void {
        $operation=$this->operation(['container_id']);$operation['code']='process';$operation['type']='custom';$operation['allowProcess']=true;
        $operations=$this->invoke('normalizeApiOperationsRegistry',[$operation],'');self::assertTrue($operations[0]['allowProcess']);
        $source=(new BuilderApiSource())->setCode('test')->setBaseUrl('https://progress.example')->setMetadata(['operations'=>$operations]);
        self::assertTrue($this->invoke('resolveApiSourceBinding',$source,'process','')['operations'][0]['allowProcess']);
    }
    public function testListOperationCannotOptInToProcessing(): void {
        $operation=$this->operation(['container_id']);$operation['allowProcess']=true;$this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiOperationsRegistry',[$operation],'');
    }
    public function testGenericContractCannotOptInToProcessing(): void {
        $operation=$this->operation(['container_id']);$operation['type']='custom';$operation['allowProcess']=true;$this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiSourceRegistryPayload',['code'=>'test','name'=>'Test','baseUrl'=>'https://progress.example','operations'=>[$operation]]);
    }
}
