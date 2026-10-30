<?php
namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntityVersion;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use PHPUnit\Framework\TestCase;

final class EntitySnapshotIntegrityGuardTest extends TestCase
{
    public function testInvalidCurrentSnapshotStopsBeforeLoadingOrChangingEntity(): void
    {
        $version=(new BuilderEntityVersion())->setStatus('current');
        $versions=$this->createMock(BuilderEntityVersionRepository::class);
        $versions->expects(self::once())->method('findByEntityCodeOrdered')->with('empresas_progress')->willReturn([$version]);
        $entities=$this->createMock(BuilderEntityRepository::class);
        $entities->expects(self::never())->method('findOneBy');
        $integrity=$this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())->method('assertBuilderEntityVersion')->with($version)
            ->willThrowException(new RuntimeHttpException('STRUCTURAL_INTEGRITY_VIOLATION','Snapshot invalido.',409));
        $reflection=new \ReflectionClass(ProgramBuilderService::class);
        $service=$reflection->newInstanceWithoutConstructor();
        foreach(['entityVersions'=>$versions,'entities'=>$entities,'integrity'=>$integrity]as$name=>$value){
            $reflection->getProperty($name)->setValue($service,$value);
        }
        $this->expectException(RuntimeHttpException::class);
        $reflection->getMethod('applyEntityConfig')->invoke($service,['code'=>'empresas_progress']);
    }
}
