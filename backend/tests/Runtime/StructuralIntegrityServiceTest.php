<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderApiSource;
use App\Entity\BuilderModule;
use App\Entity\Program;
use App\Entity\SystemRecordIntegrity;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntitySituationRepository;
use App\Repository\BuilderEntitySituationTransitionRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ImportExportMappingRepository;
use App\Repository\ImportExportMappingVersionRepository;
use App\Repository\ImportExportScheduleRepository;
use App\Repository\ProgramChangeGrantRepository;
use App\Repository\ProgramChangeRequestRepository;
use App\Repository\ProgramPublicationApprovalRepository;
use App\Repository\ProgramTestExecutionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\RuntimeLockPolicyRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Repository\SystemRecordIntegrityRepository;
use App\Repository\SystemOptionListRepository;
use App\Repository\SystemOptionRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StructuralIntegrityServiceTest extends TestCase
{
    public function testSignProgramPersistsIntegrityRecord(): void
    {
        $program = (new Program())
            ->setCode('cd0001')
            ->setTitle('Clientes')
            ->setModule('cadastros')
            ->setProgramType('crud')
            ->setScreenId('cadastros.clientes')
            ->setStatus('published');
        $this->setEntityId($program, 10);

        $integrityRepository = $this->createMock(SystemRecordIntegrityRepository::class);
        $integrityRepository->expects(self::once())
            ->method('findOneByTarget')
            ->with('builder_program', 10)
            ->willReturn(null);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$persisted) {
                $persisted = $entity;
                return $entity instanceof SystemRecordIntegrity;
            }));

        $service = $this->service($entityManager, $integrityRepository);
        $service->signProgram($program, ['source' => 'test']);

        self::assertInstanceOf(SystemRecordIntegrity::class, $persisted);
        self::assertSame('builder_program', $persisted->getTableName());
        self::assertSame(10, $persisted->getRecordId());
        self::assertSame('test', $persisted->getMetadata()['source'] ?? null);
        self::assertNotSame('', $persisted->getPayloadHash());
        self::assertNotSame('', $persisted->getSignature());
    }

    public function testAssertProgramFailsWhenCurrentPayloadDiffersFromSignedPayload(): void
    {
        $program = (new Program())
            ->setCode('cd0001')
            ->setTitle('Clientes')
            ->setModule('cadastros')
            ->setProgramType('crud')
            ->setScreenId('cadastros.clientes')
            ->setStatus('published');
        $this->setEntityId($program, 10);

        $integrity = new SystemRecordIntegrity();
        $integrity->setTableName('builder_program')
            ->setRecordId(10)
            ->setPayloadHash('hash-antigo')
            ->setSignature('assinatura-antiga');

        $integrityRepository = $this->createMock(SystemRecordIntegrityRepository::class);
        $integrityRepository->expects(self::once())
            ->method('findOneByTarget')
            ->with('builder_program', 10)
            ->willReturn($integrity);

        $service = $this->service($this->createStub(EntityManagerInterface::class), $integrityRepository);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Registro estrutural alterado fora do fluxo oficial.');

        $service->assertProgram($program);
    }

    public function testSignApiSourcePersistsIntegrityRecord(): void
    {
        $source = (new BuilderApiSource())
            ->setCode('api_clientes')
            ->setName('API Clientes')
            ->setAuthMode('bearer')
            ->setBaseUrl('https://example.test')
            ->setStatus('active')
            ->setMetadata(['providerType' => 'generic']);
        $this->setEntityId($source, 22);

        $integrityRepository = $this->createMock(SystemRecordIntegrityRepository::class);
        $integrityRepository->expects(self::once())
            ->method('findOneByTarget')
            ->with('builder_api_source', 22)
            ->willReturn(null);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$persisted) {
                $persisted = $entity;
                return $entity instanceof SystemRecordIntegrity;
            }));

        $service = $this->service($entityManager, $integrityRepository);
        $service->signApiSource($source, ['source' => 'test-api-source']);

        self::assertInstanceOf(SystemRecordIntegrity::class, $persisted);
        self::assertSame('builder_api_source', $persisted->getTableName());
        self::assertSame(22, $persisted->getRecordId());
        self::assertSame('test-api-source', $persisted->getMetadata()['source'] ?? null);
    }

    public function testSignBuilderModulePersistsIntegrityRecord(): void
    {
        $module = (new BuilderModule())
            ->setCode('cadastros')
            ->setName('Cadastros')
            ->setAbbreviation('cd')
            ->setNumberStart(1000)
            ->setNumberEnd(1999)
            ->setEnabled(true)
            ->setMetadata(['source' => 'test']);
        $this->setEntityId($module, 31);

        $integrityRepository = $this->createMock(SystemRecordIntegrityRepository::class);
        $integrityRepository->expects(self::once())
            ->method('findOneByTarget')
            ->with('builder_module', 31)
            ->willReturn(null);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$persisted) {
                $persisted = $entity;
                return $entity instanceof SystemRecordIntegrity;
            }));

        $service = $this->service($entityManager, $integrityRepository);
        $service->signBuilderModule($module, ['source' => 'test-module']);

        self::assertInstanceOf(SystemRecordIntegrity::class, $persisted);
        self::assertSame('builder_module', $persisted->getTableName());
        self::assertSame(31, $persisted->getRecordId());
        self::assertSame('test-module', $persisted->getMetadata()['source'] ?? null);
    }

    public function testSupportsExpandedStructuralTables(): void
    {
        $service = $this->service($this->createStub(EntityManagerInterface::class), $this->createStub(SystemRecordIntegrityRepository::class));

        self::assertTrue($service->supportsTableName('builder_entity_situation'));
        self::assertTrue($service->supportsTableName('builder_entity_situation_transition'));
        self::assertTrue($service->supportsTableName('system_option_list'));
        self::assertTrue($service->supportsTableName('system_option'));
        self::assertTrue($service->supportsTableName('program_change_request'));
        self::assertTrue($service->supportsTableName('program_change_grant'));
        self::assertTrue($service->supportsTableName('program_publication_approval'));
        self::assertTrue($service->supportsTableName('program_test_execution'));
    }

    private function service(EntityManagerInterface $entityManager, SystemRecordIntegrityRepository $integrities): StructuralIntegrityService
    {
        return new StructuralIntegrityService(
            $entityManager,
            $integrities,
            $this->createStub(ProgramRepository::class),
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderModuleRepository::class),
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(BuilderEntitySituationRepository::class),
            $this->createStub(BuilderEntitySituationTransitionRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $this->createStub(RuntimeLockPolicyRepository::class),
            $this->createStub(BuilderProgramOverlayRepository::class),
            $this->createStub(BuilderProgramOverlayVersionRepository::class),
            $this->createStub(ProgramChangeRequestRepository::class),
            $this->createStub(ProgramChangeGrantRepository::class),
            $this->createStub(ProgramPublicationApprovalRepository::class),
            $this->createStub(ProgramTestExecutionRepository::class),
            $this->createStub(ImportExportMappingRepository::class),
            $this->createStub(ImportExportMappingVersionRepository::class),
            $this->createStub(ImportExportScheduleRepository::class),
            $this->createStub(SystemOptionListRepository::class),
            $this->createStub(SystemOptionRepository::class),
            $this->createStub(SystemParameterRepository::class),
            $this->createStub(SystemParameterValueRepository::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(PermissionResolver::class),
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
