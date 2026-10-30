<?php
namespace App\Tests\Runtime;
use App\Entity\{AuthSubscriber,BuilderApiSource,BuilderEntity,ScreenDefinition};
use App\Repository\{AuthSubscriberRepository,BuilderApiSourceRepository,BuilderEntityRepository};
use App\Runtime\{ApiCompanyBindingRegistry,PublishedModuleCatalog,StructuralIntegrityService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ApiCompanyBindingRegistryTest extends TestCase
{
    private function registry(array $companies,string $type='api'):ApiCompanyBindingRegistry
    {
        $repo=$this->createStub(AuthSubscriberRepository::class);$repo->method('findEnabledOrdered')->willReturn($companies);
        $source=(new BuilderApiSource())->setCode('ciw')->setStatus('active')->setMetadata(['companyContext'=>['version'=>1,'adapters'=>['ciw'=>['body'=>['empresa_id'=>'externalCompanyId']]]]]);
        $sources=$this->createStub(BuilderApiSourceRepository::class);$sources->method('findOneBy')->willReturn($source);
        $entities=$this->createStub(BuilderEntityRepository::class);$entities->method('findOneBy')->willReturn((new BuilderEntity())->setCode('entity')->setEntityType($type));
        $catalog=$this->createStub(PublishedModuleCatalog::class);$catalog->method('screenIds')->willReturn(['screen']);$catalog->method('screen')->willReturn((new ScreenDefinition())->setDefinition(['program'=>['entity'=>'entity']]));
        return new ApiCompanyBindingRegistry($repo,$sources,$entities,$catalog,$this->createStub(StructuralIntegrityService::class),$this->createStub(EntityManagerInterface::class));
    }
    private function company(string $owner='default',int $revision=1):AuthSubscriber
    {
        return (new AuthSubscriber())->setCode('ima')->setName('Empresa IMA')->setMetadata(['apiCompanyBindings'=>[['key'=>'ima','ownerTenantId'=>$owner,'moduleId'=>'ciw','apiSourceCode'=>'ciw','externalCompanyId'=>1,'ems2Code'=>1,'ems5Code'=>100,'revision'=>$revision,'enabled'=>true]]]);
    }
    public function testBindingsAreOwnedByTenantAndOnlyApiModulesAreEligible():void
    {
        $registry=$this->registry([$this->company()]);self::assertTrue($registry->moduleUsesApi('default','ciw'));
        self::assertSame(100,$registry->findEnabled('default','ciw','ima','ciw')['ems5Code']);
        self::assertSame([],$registry->bindings('other','ciw'));
        self::assertFalse($this->registry([$this->company()],'persistence')->moduleUsesApi('default','ciw'));
    }
    public function testSourceMappingChangesInvalidateTheSelectionFingerprint():void
    {
        $r=$this->registry([$this->company()]);$bindings=$r->bindings('default','ciw');
        self::assertArrayHasKey('sourceContextHash',$bindings[0]);
        $before=$r->revisionFingerprint($bindings,'ima');$bindings[0]['sourceContextHash']='changed';
        self::assertNotSame($before,$r->revisionFingerprint($bindings,'ima'));
    }
    public function testRevisionFingerprintChangesAndDuplicateBindingsFailClosed():void
    {
        $r=$this->registry([$this->company()]);$first=$r->revisionFingerprint($r->bindings('default','ciw'),'ima');
        self::assertNotSame($first,$r->revisionFingerprint($this->registry([$this->company('default',2)])->bindings('default','ciw'),'ima'));
        $this->expectException(RuntimeHttpException::class);$this->registry([$this->company(),$this->company()])->bindings('default','ciw');
    }
}
