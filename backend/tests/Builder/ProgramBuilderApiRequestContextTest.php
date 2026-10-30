<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderApiRequestContextTest extends TestCase
{
    private function invoke(string $method, mixed ...$args): mixed {
        $class = new \ReflectionClass(ProgramBuilderService::class);
        return $class->getMethod($method)->invoke($class->newInstanceWithoutConstructor(), ...$args);
    }
    private function operation(mixed $fields): array {
        return ['code'=>'read', 'name'=>'Consultar', 'type'=>'list', 'path'=>'https://progress.example/container-itens', 'method'=>'POST', 'itemsPath'=>'data', 'requestContextFields'=>$fields];
    }
    public function testRegistryAndBindingsPreserveDeclaredRequestContextOnly(): void {
        $operations=$this->invoke('normalizeApiOperationsRegistry', [$this->operation(['container_id'])], '');
        self::assertSame(['container_id'], $operations[0]['requestContextFields']);
        $source=(new BuilderApiSource())->setCode('test')->setBaseUrl('https://progress.example')->setMetadata(['operations'=>$operations]);
        $binding=$this->invoke('resolveApiSourceBinding',$source,'read','read');
        self::assertSame(['container_id'],$binding['listEndpoint']['requestContextFields']);
        self::assertSame(['container_id'],$binding['detailEndpoint']['requestContextFields']);
        self::assertSame(['container_id'],$binding['operations'][0]['requestContextFields']);
        $normalized=$this->invoke('normalizeApiSource',$binding);
        self::assertSame(['container_id'],$normalized['listEndpoint']['requestContextFields']);
    }
    public static function invalidFields(): array {
        return [['container_id'],[['bad.name']], [['Container_id']], [[' _id']], [array_map(fn ($i) => 'field_' . $i, range(1,9))], [['container_id','container_id']], [[['field'=>'container_id']]], [range(1,11)], [null]];
    }
    #[DataProvider('invalidFields')]
    public function testRejectsInvalidRequestContext(mixed $fields): void {
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiOperationsRegistry',[$this->operation($fields)],'');
    }

    public function testContextualOperationRejectsGetMethod(): void {
        $operation=$this->operation(['container_id']);$operation['method']='GET';
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeApiOperationsRegistry',[$operation],'');
    }
    public function testEightLowercaseContextFieldsAreAccepted(): void {
        $fields=array_map(fn ($i) => 'field_' . $i,range(1,8));
        self::assertSame($fields,$this->invoke('normalizeApiOperationsRegistry',[$this->operation($fields)],'')[0]['requestContextFields']);
    }
    public function testEntityBindingRejectsMissingOrNonIntegerContextFields(): void {
        $payload=['code'=>'context_records','name'=>'Records','entityType'=>'api','skipStructureValidation'=>true,
          'apiSource'=>['listEndpoint'=>['url'=>'https://progress.example/rows','method'=>'POST','requestContextFields'=>['container_id']],'listResponse'=>['itemsPath'=>'data']],
          'fields'=>[['code'=>'id','label'=>'ID','dataType'=>'integer','primaryKey'=>true,'apiJsonPath'=>'id'],['code'=>'container_id','label'=>'Container','dataType'=>'string','apiJsonPath'=>'container_id']]];
        $this->expectException(RuntimeHttpException::class);
        $this->invoke('normalizeEntityPayload',$payload);
    }

}
