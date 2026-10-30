<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeApiEntityActionService,RuntimeHttpException};
use PHPUnit\Framework\TestCase;
final class RuntimeProcessIntegerCollectionTest extends TestCase {
 private function value(array $field,mixed $v):mixed {$r=new \ReflectionClass(RuntimeApiEntityActionService::class);return $r->getMethod('normalizeProcessValue')->invoke($r->newInstanceWithoutConstructor(),$field,$v);}
 private function field():array{return ['code'=>'item_ids','dataType'=>'json','required'=>true,'collection'=>['itemType'=>'integer']];}
 public function testNativeIntegerCollectionIsAvailableToProcess():void {self::assertSame([3,2,2147483647],$this->value($this->field(),[3,2,2147483647]));}
 public function testInvalidIdsNeverReachProgress():void {foreach([[],[1,1],[0],[-1],[2147483648],['2'],[1.5],[true],['id'=>1],range(1,101),null,'1,2']as$v){try{$this->value($this->field(),$v);self::fail('Invalid accepted');}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());}}}
 public function testFreeJsonIsStillRejected():void {$f=$this->field();$f['collection']=null;$this->expectException(RuntimeHttpException::class);$this->value($f,[1,2]);}
 public function testOptionalEmptyCollectionDoesNotAuthorizeExecution():void {$f=$this->field();$f['required']=false;self::assertSame([],$this->value($f,[]));}
 public function testExistingScalarContractUnchanged():void {self::assertSame(2,$this->value(['dataType'=>'integer','required'=>true],'2'));}
}
