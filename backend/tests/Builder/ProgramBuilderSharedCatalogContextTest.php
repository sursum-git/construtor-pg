<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderApiSource;
use App\Repository\BuilderApiSourceRepository;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class ProgramBuilderSharedCatalogContextTest extends TestCase
{
    private function payload(): array {
        return ['code'=>'parent_records','name'=>'Parent records','entityType'=>'api','skipStructureValidation'=>true,
            'apiSource'=>['listEndpoint'=>['url'=>'https://progress.example/rows','method'=>'POST'],'listResponse'=>['itemsPath'=>'data'],
                'operations'=>[['code'=>'child_options','name'=>'Child options','type'=>'list','method'=>'POST','path'=>'https://progress.example/options','itemsPath'=>'data','requestContextFields'=>['item_id']]]],
            'fields'=>[['code'=>'id','label'=>'ID','dataType'=>'integer','primaryKey'=>true,'apiJsonPath'=>'id']]];
    }
    private function normalize(array $payload): array {
        $r=new \ReflectionClass(ProgramBuilderService::class);
        $service=$r->newInstanceWithoutConstructor();
        $operations=$payload['apiSource']['operations'];
        $operations[]=['code'=>'parent_read','name'=>'Parent read','type'=>'list','method'=>'POST','path'=>'https://progress.example/rows','itemsPath'=>'data','requestContextFields'=>$payload['apiSource']['listEndpoint']['requestContextFields']??[]];
        $source=(new BuilderApiSource())->setCode('shared')->setName('Shared catalog')->setBaseUrl('https://progress.example')->setMetadata(['operations'=>$operations]);
        $repository=$this->createStub(BuilderApiSourceRepository::class);$repository->method('findOneBy')->willReturn($source);
        $r->getProperty('apiSources')->setValue($service,$repository);
        unset($payload['apiSource']);$payload['apiSourceCode']='shared';$payload['apiListOperationCode']='parent_read';$payload['apiDetailOperationCode']='parent_read';
        return $r->getMethod('normalizeEntityPayload')->invoke($service,$payload);
    }
    public function testUnboundCatalogOperationDoesNotAddFieldsToParentEntity(): void {
        $result=$this->normalize($this->payload());
        self::assertSame(['id'],array_column($result['fields'],'code'));
        self::assertSame(['item_id'],$result['apiSource']['operations'][0]['requestContextFields']);
    }
    public function testBoundCrudContextStillRequiresEntityInteger(): void {
        $payload=$this->payload();$payload['apiSource']['listEndpoint']['requestContextFields']=['item_id'];
        $this->expectException(RuntimeHttpException::class);$this->normalize($payload);
    }
    public function testReferencedOptionsOperationStillRequiresEntityInteger(): void {
        $payload=$this->payload();$payload['fields'][0]['options']['api']['optionsSource']=['operationCode'=>'child_options','valueField'=>'id','textField'=>'name'];
        $this->expectException(RuntimeHttpException::class);$this->normalize($payload);
    }
    public function testReferencedLookupOperationStillRequiresEntityInteger(): void {
        $payload=$this->payload();$payload['fields'][0]['apiLookupResolver']=['operationCode'=>'child_options','sourceField'=>'id','requestParam'=>'ids','valuePath'=>'name','responseItemsPath'=>'data','matchField'=>'id'];
        $this->expectException(RuntimeHttpException::class);$this->normalize($payload);
    }
    public function testReferencedOptionsContextAcceptsDeclaredInteger(): void {
        $payload=$this->payload();$payload['fields'][0]['options']['api']['optionsSource']=['operationCode'=>'child_options','valueField'=>'id','textField'=>'name'];
        $payload['fields'][]=['code'=>'item_id','label'=>'Item','dataType'=>'integer','apiJsonPath'=>'item_id'];
        self::assertCount(2,$this->normalize($payload)['fields']);
    }
}
