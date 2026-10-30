<?php
namespace App\Tests\Runtime;
use App\Entity\{BuilderEntity,RuntimeEndpoint};
use App\Repository\{BuilderEntityRepository,RuntimeEndpointRepository};
use App\Runtime\{ApiCompanyAuditClassifier,StructuralIntegrityService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
final class ApiCompanyAuditClassifierTest extends TestCase {
 public function testPendingMarkerUsesSignedEndpointAndSignedEntityBeforeAnyAuthorization():void {
  $entity=(new BuilderEntity())->setEntityType('api')->setMetadata(['apiBinding'=>['sourceCode'=>'fixture'],'apiCompanyContext'=>['version'=>1,'moduleId'=>'ciw','operations'=>['read'=>['mode'=>'company','adapter'=>'company']]]]);
  $entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
  $endpoint=(new RuntimeEndpoint())->setHandler('entity.api.crud')->setConfig(['entityCode'=>'a']);$endpoints=$this->createStub(RuntimeEndpointRepository::class);$endpoints->method('findEnabled')->willReturn($endpoint);
  $integrity=$this->createMock(StructuralIntegrityService::class);$integrity->expects(self::once())->method('assertEndpoint')->with($endpoint);$integrity->expects(self::once())->method('assertBuilderEntity')->with($entity);
  $classifier=new ApiCompanyAuditClassifier($entities,$endpoints,$integrity,$this->createStub(EntityManagerInterface::class));
  self::assertSame(['version'=>1,'source'=>'authenticated-api-company','moduleId'=>'ciw','sourceCode'=>'fixture','mode'=>'pending'],$classifier->pending('ciw.a','read','entity.api.crud'));
 }
 public function testRemovedCompanyConfigurationDoesNotBypassStructuralIntegrity():void {
  $entity=(new BuilderEntity())->setEntityType('api')->setMetadata([]);$entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn($entity);
  $endpoint=(new RuntimeEndpoint())->setHandler('entity.api.crud')->setConfig(['entityCode'=>'a']);$endpoints=$this->createStub(RuntimeEndpointRepository::class);$endpoints->method('findEnabled')->willReturn($endpoint);
  $integrity=$this->createStub(StructuralIntegrityService::class);$integrity->method('assertBuilderEntity')->willThrowException(new RuntimeHttpException('INTEGRITY_INVALID','Tampered',403));
  $classifier=new ApiCompanyAuditClassifier($entities,$endpoints,$integrity,$this->createStub(EntityManagerInterface::class));
  $this->expectException(RuntimeHttpException::class);$this->expectExceptionMessage('Tampered');$classifier->pending('ciw.a','read','entity.api.crud');
 }
}
