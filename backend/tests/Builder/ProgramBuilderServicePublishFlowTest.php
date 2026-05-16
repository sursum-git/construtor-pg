<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderProgramVersion;
use App\Entity\Program;
use App\Entity\ProgramChangeGrant;
use App\Entity\ProgramChangeRequest;
use App\Entity\ScreenDefinition;
use App\Odoo\OdooClient;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\ProgramOverlayService;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use App\Runtime\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramBuilderServicePublishFlowTest extends TestCase
{
    public function testPublishVersionConsumesGrantAndReturnsPublishedProgram(): void
    {
        $version = (new BuilderProgramVersion())
            ->setProgramCode('cad.clientes')
            ->setProgramTitle('Clientes')
            ->setModule('cadastros')
            ->setPageType('custom')
            ->setBuilderEntityCode('')
            ->setScreenId('cad.clientes')
            ->setVersion('1.2.0')
            ->setStatus('draft')
            ->setProgramOrigin('standard')
            ->setOwnerScope('system')
            ->setCustomizationPolicy('overlay_only')
            ->setBuilderConfig([
                'publicationPolicy' => [
                    'allowedDatabaseEnvironments' => ['test'],
                ],
            ])
            ->setGeneratedDefinition([
                'schemaVersion' => '1.0',
                'program' => ['title' => 'Clientes'],
                'runtime' => [],
            ]);
        $this->setEntityId($version, 77);

        $grant = (new ProgramChangeGrant())
            ->setProgramCode('cad.clientes')
            ->setBuilderEntityCode(null)
            ->setGrantedToUserId('tester')
            ->setAllowedActions(['edit', 'publish'])
            ->setStatus('active')
            ->setValidUntilPublish(true);
        $request = (new ProgramChangeRequest())
            ->setRequestCode('CAD.CLIENTES-REQ')
            ->setProgramCode('cad.clientes')
            ->setBuilderEntityCode(null)
            ->setRequestedBy('tester')
            ->setRequestedActions(['edit', 'publish'])
            ->setStatus('approved');
        $grant->setRequest($request);
        $this->setEntityId($grant, 91);
        $this->setEntityId($request, 45);

        $versions = $this->createMock(BuilderProgramVersionRepository::class);
        $versions->method('find')->with(77)->willReturn($version);
        $versions->expects(self::exactly(2))
            ->method('findByProgramCodeOrdered')
            ->with('cad.clientes')
            ->willReturn([$version]);

        $programHolder = null;
        $programs = $this->createMock(ProgramRepository::class);
        $programs->expects(self::exactly(2))
            ->method('findOneBy')
            ->with(['code' => 'cad.clientes'])
            ->willReturnCallback(function () use (&$programHolder) {
                return $programHolder;
            });

        $screens = $this->createMock(ScreenDefinitionRepository::class);
        $screens->expects(self::once())
            ->method('findOneBy')
            ->with(['screenId' => 'cad.clientes'])
            ->willReturn(null);

        $endpoints = $this->createMock(RuntimeEndpointRepository::class);
        $endpoints->expects(self::exactly(2))
            ->method('findBy')
            ->with(['screenId' => 'cad.clientes'])
            ->willReturn([]);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted, &$programHolder) {
                $persisted[] = $entity;
                if ($entity instanceof Program && $entity->getId() === null) {
                    $this->setEntityId($entity, 501);
                    $programHolder = $entity;
                }
                if ($entity instanceof ScreenDefinition && $entity->getId() === null) {
                    $this->setEntityId($entity, 601);
                }
            });
        $entityManager->expects(self::exactly(2))->method('flush');

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::exactly(2))->method('assertProgramVersion')->with($version);
        $integrity->expects(self::once())->method('assertProgram')->with(self::callback(fn (Program $program): bool => $program->getCode() === 'cad.clientes'));
        $integrity->expects(self::once())->method('signProgram')->with(self::callback(fn (Program $program): bool => $program->getCode() === 'cad.clientes'), ['source' => 'publishVersion']);
        $integrity->expects(self::once())->method('signScreen')->with(self::callback(fn (ScreenDefinition $screen): bool => $screen->getScreenId() === 'cad.clientes'), ['source' => 'publishVersion']);
        $integrity->expects(self::once())->method('signProgramVersion')->with($version, ['source' => 'publishVersion', 'grantId' => 91]);

        $governance = $this->createMock(ProgramGovernanceService::class);
        $governance->expects(self::once())->method('assertCanPublish')->with($version)->willReturn($grant);
        $governance->expects(self::once())->method('consumeGrant')->with($grant);
        $governance->expects(self::once())->method('governanceSummary')->with($version)->willReturn([
            'requiresGovernance' => true,
            'grant' => null,
            'approval' => ['status' => 'approved', 'testExecutionBundleId' => 'bundle-77'],
        ]);

        $environment = $this->createMock(RuntimeEnvironmentIdentityResolver::class);
        $environment->expects(self::once())
            ->method('resolve')
            ->willReturn([
                'databaseIdentity' => 'db:test-1',
                'databaseEnvironment' => 'test',
            ]);

        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);

        $service = new ProgramBuilderService(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $this->createStub(BuilderModuleRepository::class),
            $this->createStub(BuilderFieldRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $versions,
            $programs,
            $screens,
            $endpoints,
            $entityManager,
            $integrity,
            $governance,
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $environment,
            $permissions,
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
        );

        $result = $service->publishVersion(77);

        self::assertSame('cad.clientes', $result['program']['code'] ?? null);
        self::assertSame('published', $result['program']['status'] ?? null);
        self::assertCount(1, $result['versions'] ?? []);
        self::assertSame('published', $result['versions'][0]['status'] ?? null);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
