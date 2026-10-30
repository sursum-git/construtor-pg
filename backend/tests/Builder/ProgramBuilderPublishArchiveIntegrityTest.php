<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderProgramVersion;
use App\Entity\Program;
use App\Entity\ScreenDefinition;
use App\Entity\SystemRecordIntegrity;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\SystemRecordIntegrityRepository;
use App\Runtime\StructuralIntegrityService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProgramBuilderPublishArchiveIntegrityTest extends TestCase {
 private function object(string $class,array $overrides=[]): object {
  $r=new \ReflectionClass($class);$args=[];
  foreach($r->getConstructor()->getParameters()as$p){$type=$p->getType()->getName();$args[]=$overrides[$type]??$this->createStub($type);}return $r->newInstanceArgs($args);
 }
 private function id(object $value,int $id): void {(new \ReflectionProperty($value,'id'))->setValue($value,$id);}
 private function scenario(): array {
  $previous=(new BuilderProgramVersion())->setProgramCode('cd0093')->setProgramTitle('Empresas por despesa')->setModule('cadastros')->setPageType('custom')->setScreenId('ciw.empresas-despesa')->setBuilderEntityCode('')->setVersion('1.0.0')->setStatus('published')->setProgramOrigin('customer_custom')->setOwnerScope('subscriber')->setGeneratedDefinition(['schemaVersion'=>'1.0','program'=>['title'=>'Empresas por despesa'],'runtime'=>[]]);$this->id($previous,5);
  $next=clone $previous;$this->id($next,7);$next->setVersion('1.0.1')->setStatus('draft')->setBuilderConfig(['publicationPolicy'=>['allowedDatabaseEnvironments'=>['test']]]);
  $program=(new Program())->setCode('cd0093')->setTitle('Empresas por despesa')->setModule('cadastros')->setProgramType('custom')->setScreenId('ciw.empresas-despesa')->setStatus('published');$this->id($program,50);
  $versions=$this->createStub(BuilderProgramVersionRepository::class);$versions->method('find')->willReturn($next);$versions->method('findByProgramCodeOrdered')->willReturn([$next,$previous]);
  $programs=$this->createStub(ProgramRepository::class);$programs->method('findOneBy')->willReturn($program);
  $screens=$this->createStub(ScreenDefinitionRepository::class);$screens->method('findOneBy')->willReturn(null);
  $endpoints=$this->createStub(RuntimeEndpointRepository::class);$endpoints->method('findBy')->willReturn([]);
  $stored=[];$repo=$this->createStub(SystemRecordIntegrityRepository::class);$repo->method('findOneByTarget')->willReturnCallback(function($table,$id)use(&$stored){return $stored[$table.'.'.$id]??null;});
  $em=$this->createStub(EntityManagerInterface::class);$em->method('persist')->willReturnCallback(function($entity)use(&$stored){if($entity instanceof SystemRecordIntegrity){$stored[$entity->getTableName().'.'.$entity->getRecordId()]=$entity;}if($entity instanceof ScreenDefinition && !$entity->getId())$this->id($entity,60);});
  $permissions=$this->createStub(PermissionResolver::class);$permissions->method('hasPermission')->willReturn(true);
  $integrity=$this->object(StructuralIntegrityService::class,[EntityManagerInterface::class=>$em,SystemRecordIntegrityRepository::class=>$repo,PermissionResolver::class=>$permissions]);
  $integrity->signProgramVersion($previous,['source'=>'fixture.previous']);$integrity->signProgramVersion($next,['source'=>'fixture.next']);$integrity->signProgram($program);
  $environment=$this->createStub(RuntimeEnvironmentIdentityResolver::class);$environment->method('resolve')->willReturn(['databaseEnvironment'=>'test']);
  $service=$this->object(ProgramBuilderService::class,[EntityManagerInterface::class=>$em,BuilderProgramVersionRepository::class=>$versions,ProgramRepository::class=>$programs,ScreenDefinitionRepository::class=>$screens,RuntimeEndpointRepository::class=>$endpoints,StructuralIntegrityService::class=>$integrity,PermissionResolver::class=>$permissions,RuntimeEnvironmentIdentityResolver::class=>$environment]);
  return [$service,$integrity,$previous,$next,$repo];
 }
 public function testPublishingNewVersionArchivesAndSignsPreviousVersion(): void {
  [$service,$integrity,$previous,$next,$repo]=$this->scenario();$result=$service->publishVersion(7);
  self::assertSame('archived',$previous->getStatus());self::assertSame('published',$next->getStatus());
  $integrity->assertProgramVersion($previous);$integrity->assertProgramVersion($next);
  self::assertSame(['published','archived'],array_column($result['versions'],'status'));
  self::assertSame(7,$repo->findOneByTarget('builder_program_version',5)->getMetadata()['replacedByVersionId']);
 }
 public function testTamperedPreviousVersionIsRejectedBeforeArchivingOrPublishing(): void {
  [$service,$integrity,$previous,$next]=$this->scenario();$previous->setProgramTitle('Alterado fora do fluxo');
  try{$service->publishVersion(7);self::fail('Assinatura adulterada deveria impedir publicacao.');}catch(RuntimeHttpException $error){self::assertSame('STRUCTURAL_INTEGRITY_VIOLATION',$error->getErrorCode());}
  self::assertSame('published',$previous->getStatus());self::assertSame('draft',$next->getStatus());
 }
}
