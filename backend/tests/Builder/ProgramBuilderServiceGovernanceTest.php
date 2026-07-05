<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderField;
use App\Entity\BuilderModule;
use App\Entity\Program;
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
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use App\Entity\BuilderProgramVersion;
use App\Runtime\PermissionResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramBuilderServiceGovernanceTest extends TestCase
{
    public function testPublicationEnvironmentPolicyBlocksUnsupportedEnvironment(): void
    {
        $version = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setBuilderConfig([
                'publicationPolicy' => [
                    'allowedDatabaseEnvironments' => ['prod'],
                ],
            ]);

        $environment = $this->createMock(RuntimeEnvironmentIdentityResolver::class);
        $environment->expects(self::once())
            ->method('resolve')
            ->willReturn([
                'databaseIdentity' => 'db:test-1',
                'databaseEnvironment' => 'test',
            ]);

        $service = $this->service($environment);

        $method = new \ReflectionMethod($service, 'assertPublicationEnvironmentAllowed');
        $method->setAccessible(true);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('A publicacao nao esta autorizada para o ambiente atual.');

        $method->invoke($service, $version);
    }

    public function testStandardProgramCannotBeCreatedWithSubscriberScope(): void
    {
        $service = $this->serviceForNormalizePayload(null);
        $method = new \ReflectionMethod($service, 'normalizeBuilderPayload');
        $method->setAccessible(true);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Programa padrao nao pode ser vinculado a assinante especifico.');

        $method->invoke($service, [
            'programCode' => 'cd1001',
            'programTitle' => 'Clientes',
            'module' => 'cadastros',
            'pageType' => 'custom',
            'screenId' => 'cad.clientes',
            'version' => '1.0.0',
            'programOrigin' => 'standard',
            'ownerScope' => 'system',
            'subscriberId' => 'tenant-a',
            'customEntryUrl' => '/production/app.html?screenId=cd1001',
        ], null);
    }

    public function testStandardProgramCannotTransitionToCustomerCustom(): void
    {
        $existing = (new Program())
            ->setCode('cd1001')
            ->setProgramOrigin('standard')
            ->setOwnerScope('system');
        $service = $this->serviceForNormalizePayload($existing);
        $method = new \ReflectionMethod($service, 'normalizeBuilderPayload');
        $method->setAccessible(true);

        try {
            $method->invoke($service, [
                'programCode' => 'cd1001',
                'programTitle' => 'Clientes',
                'module' => 'cadastros',
                'pageType' => 'custom',
                'screenId' => 'cad.clientes',
                'version' => '1.0.0',
                'programOrigin' => 'customer_custom',
                'ownerScope' => 'subscriber',
                'subscriberId' => 'tenant-a',
                'baseProgramCode' => 'cd1001',
                'baseProgramVersionId' => 12,
                'customEntryUrl' => '/production/app.html?screenId=cd1001-cliente',
            ], null);
            self::fail('Era esperado bloqueio de transicao ilegal.');
        } catch (\ReflectionException $error) {
            throw $error;
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROGRAM_ORIGIN_TRANSITION_NOT_ALLOWED', $error->getErrorCode());
        }
    }

    public function testCustomerProgramCannotTransitionToStandard(): void
    {
        $existing = (new Program())
            ->setCode('cd1001')
            ->setProgramOrigin('customer_custom')
            ->setOwnerScope('subscriber')
            ->setSubscriberId('tenant-a')
            ->setBaseProgramCode('cd0001')
            ->setBaseProgramVersionId(10);
        $service = $this->serviceForNormalizePayload($existing);
        $method = new \ReflectionMethod($service, 'normalizeBuilderPayload');
        $method->setAccessible(true);

        try {
            $method->invoke($service, [
                'programCode' => 'cd1001',
                'programTitle' => 'Clientes',
                'module' => 'cadastros',
                'pageType' => 'custom',
                'screenId' => 'cad.clientes',
                'version' => '1.0.0',
                'programOrigin' => 'standard',
                'ownerScope' => 'system',
                'customEntryUrl' => '/production/app.html?screenId=cd1001',
            ], null);
            self::fail('Era esperado bloqueio de transicao ilegal.');
        } catch (\ReflectionException $error) {
            throw $error;
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROGRAM_ORIGIN_TRANSITION_NOT_ALLOWED', $error->getErrorCode());
        }
    }

    public function testPersistentEntityRequiresExplicitGlobalTableConfirmation(): void
    {
        $service = $this->serviceForNormalizePayload(null);
        $method = new \ReflectionMethod($service, 'normalizeEntityPayload');
        $method->setAccessible(true);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Marque explicitamente quando a tabela persistente for global e compartilhada entre assinantes.');

        $method->invoke($service, [
            'code' => 'estado',
            'name' => 'Estados',
            'entityType' => 'persistence',
            'tableName' => 'estado',
            'structureModuleCode' => 'cadastros',
            'structureType' => 'main',
            'structureBaseNumber' => 1001,
            'fields' => [
                ['code' => 'id', 'columnName' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true],
                ['code' => 'nome', 'columnName' => 'nome', 'label' => 'Nome', 'dataType' => 'string'],
            ],
            'subscriberIsolationMode' => 'none',
            'subscriberGlobalTable' => false,
        ]);
    }

    public function testPersistentEntityAllowsExplicitGlobalTableConfirmation(): void
    {
        $service = $this->serviceForNormalizePayload(null);
        $method = new \ReflectionMethod($service, 'normalizeEntityPayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'code' => 'estado',
            'name' => 'Estados',
            'entityType' => 'persistence',
            'tableName' => 'estado',
            'structureModuleCode' => 'cadastros',
            'structureType' => 'main',
            'structureBaseNumber' => 1001,
            'fields' => [
                ['code' => 'id', 'columnName' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true],
                ['code' => 'nome', 'columnName' => 'nome', 'label' => 'Nome', 'dataType' => 'string'],
            ],
            'subscriberIsolationMode' => 'none',
            'subscriberGlobalTable' => true,
        ]);

        self::assertSame('none', $result['subscriberIsolation']['mode']);
        self::assertTrue($result['subscriberIsolation']['globalTable']);
    }

    public function testSaveEntityFlushesStructuralSignatures(): void
    {
        $module = (new BuilderModule())
            ->setCode('cadastros')
            ->setName('Cadastros')
            ->setAbbreviation('cd')
            ->setNumberStart(1)
            ->setNumberEnd(999);

        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'cadastros'] ? $module : null
        );

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn(null);

        $fields = $this->createStub(BuilderFieldRepository::class);
        $fields->method('findOneBy')->willReturn(null);

        $entityVersions = $this->createStub(BuilderEntityVersionRepository::class);
        $entityVersions->method('findByEntityCodeOrdered')->willReturn([]);
        $entityVersions->method('nextRevision')->willReturn(1);

        $persistedFields = [];
        $flushes = 0;
        $events = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persistedFields): void {
            if ($entity instanceof BuilderEntity && $entity->getId() === null) {
                $this->setEntityId($entity, 52);
            }
            if ($entity instanceof BuilderField && $entity->getId() === null) {
                $this->setEntityId($entity, 700 + count($persistedFields));
                $persistedFields[] = $entity;
            }
            if ($entity instanceof BuilderEntityVersion && $entity->getId() === null) {
                $this->setEntityId($entity, 19);
            }
        });
        $entityManager->method('refresh')->willReturnCallback(function (object $entity) use (&$persistedFields): void {
            if ($entity instanceof BuilderEntity) {
                foreach ($persistedFields as $field) {
                    $entity->addField($field);
                }
            }
        });
        $entityManager->expects(self::exactly(4))
            ->method('flush')
            ->willReturnCallback(function () use (&$flushes, &$events): void {
                ++$flushes;
                $events[] = 'flush';
            });

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())
            ->method('signBuilderEntity')
            ->with(self::callback(static fn (BuilderEntity $entity): bool => $entity->getCode() === 'tipo_produto'))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_entity';
            });
        $integrity->expects(self::exactly(2))
            ->method('signBuilderField')
            ->with(self::callback(static fn (BuilderField $field): bool => in_array($field->getCode(), ['id', 'descricao'], true)))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_field';
            });
        $integrity->expects(self::once())
            ->method('signBuilderEntityVersion')
            ->with(self::isInstanceOf(BuilderEntityVersion::class))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_version';
            });

        $governance = $this->createStub(ProgramGovernanceService::class);
        $governance->method('assertCanEditEntity')->willReturn(null);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $permissions->method('getTenantId')->willReturn('default');
        $permissions->method('getUserId')->willReturn('tester');

        $service = new ProgramBuilderService(
            $entities,
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $modules,
            $fields,
            $entityVersions,
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(ProgramRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $entityManager,
            $integrity,
            $governance,
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
            $permissions,
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );

        $service->saveEntity([
            'code' => 'tipo_produto',
            'name' => 'Tipo de Produto',
            'entityType' => 'persistence',
            'tableName' => 't990',
            'originalTableName' => 't990',
            'structureModuleCode' => 'cadastros',
            'structureType' => 'main',
            'structureBaseNumber' => 990,
            'subscriberIsolationMode' => 'none',
            'subscriberGlobalTable' => true,
            'createPhysicalTable' => false,
            'fields' => [
                ['code' => 'id', 'columnName' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true],
                ['code' => 'descricao', 'columnName' => 'descricao', 'label' => 'Descricao', 'dataType' => 'string', 'required' => true],
            ],
        ]);

        self::assertSame(4, $flushes);
        self::assertSame(['sign_entity', 'sign_field', 'sign_field', 'sign_version', 'flush'], array_slice($events, -5));
    }

    public function testRestoreEntityVersionFlushesStructuralSignatures(): void
    {
        $sourceVersion = (new BuilderEntityVersion())
            ->setBuilderEntityCode('tipo_produto')
            ->setEntityName('Tipo de Produto')
            ->setEntityType('persistence')
            ->setTableName('t990')
            ->setRevision(1)
            ->setSnapshot([
                'code' => 'tipo_produto',
                'name' => 'Tipo de Produto',
                'entityType' => 'persistence',
                'tableName' => 't990',
                'originalTableName' => 't990',
                'structureModuleCode' => 'cadastros',
                'structureType' => 'main',
                'structureBaseNumber' => 990,
                'subscriberIsolationMode' => 'none',
                'subscriberGlobalTable' => true,
                'createPhysicalTable' => false,
                'fields' => [
                    ['code' => 'id', 'columnName' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true],
                    ['code' => 'descricao', 'columnName' => 'descricao', 'label' => 'Descricao', 'dataType' => 'string', 'required' => true],
                ],
            ]);
        $this->setEntityId($sourceVersion, 19);

        $module = (new BuilderModule())
            ->setCode('cadastros')
            ->setName('Cadastros')
            ->setAbbreviation('cd')
            ->setNumberStart(1)
            ->setNumberEnd(999);

        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'cadastros'] ? $module : null
        );

        $entities = $this->createStub(BuilderEntityRepository::class);
        $entities->method('findOneBy')->willReturn(null);

        $fields = $this->createStub(BuilderFieldRepository::class);
        $fields->method('findOneBy')->willReturn(null);

        $entityVersions = $this->createStub(BuilderEntityVersionRepository::class);
        $entityVersions->method('find')->willReturnCallback(
            static fn (int $id): ?BuilderEntityVersion => $id === 19 ? $sourceVersion : null
        );
        $entityVersions->method('findByEntityCodeOrdered')->willReturn([]);
        $entityVersions->method('nextRevision')->willReturn(2);

        $persistedFields = [];
        $flushes = 0;
        $events = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persistedFields): void {
            if ($entity instanceof BuilderEntity && $entity->getId() === null) {
                $this->setEntityId($entity, 52);
            }
            if ($entity instanceof BuilderField && $entity->getId() === null) {
                $this->setEntityId($entity, 700 + count($persistedFields));
                $persistedFields[] = $entity;
            }
            if ($entity instanceof BuilderEntityVersion && $entity->getId() === null) {
                $this->setEntityId($entity, 20);
            }
        });
        $entityManager->method('refresh')->willReturnCallback(function (object $entity) use (&$persistedFields): void {
            if ($entity instanceof BuilderEntity) {
                foreach ($persistedFields as $field) {
                    $entity->addField($field);
                }
            }
        });
        $entityManager->expects(self::exactly(4))
            ->method('flush')
            ->willReturnCallback(function () use (&$flushes, &$events): void {
                ++$flushes;
                $events[] = 'flush';
            });

        $integrity = $this->createMock(StructuralIntegrityService::class);
        $integrity->expects(self::once())
            ->method('signBuilderEntity')
            ->with(self::callback(static fn (BuilderEntity $entity): bool => $entity->getCode() === 'tipo_produto'))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_entity';
            });
        $integrity->expects(self::exactly(2))
            ->method('signBuilderField')
            ->with(self::callback(static fn (BuilderField $field): bool => in_array($field->getCode(), ['id', 'descricao'], true)))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_field';
            });
        $integrity->expects(self::once())
            ->method('signBuilderEntityVersion')
            ->with(self::isInstanceOf(BuilderEntityVersion::class))
            ->willReturnCallback(function () use (&$events): void {
                $events[] = 'sign_version';
            });

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('hasPermission')->willReturn(true);
        $permissions->method('getTenantId')->willReturn('default');
        $permissions->method('getUserId')->willReturn('tester');

        $service = new ProgramBuilderService(
            $entities,
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $modules,
            $fields,
            $entityVersions,
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(ProgramRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $entityManager,
            $integrity,
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(RuntimeEnvironmentIdentityResolver::class),
            $permissions,
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );

        $service->restoreEntityVersion(19);

        self::assertSame(4, $flushes);
        self::assertSame(['sign_entity', 'sign_field', 'sign_field', 'sign_version', 'flush'], array_slice($events, -5));
    }

    private function service(RuntimeEnvironmentIdentityResolver $environment): ProgramBuilderService
    {
        return new ProgramBuilderService(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $this->createStub(BuilderModuleRepository::class),
            $this->createStub(BuilderFieldRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(ProgramRepository::class),
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $environment,
            $this->createStub(PermissionResolver::class),
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );
    }

    private function serviceForNormalizePayload(?Program $existingProgram): ProgramBuilderService
    {
        $module = (new BuilderModule())
            ->setCode('cadastros')
            ->setName('Cadastros')
            ->setAbbreviation('cd')
            ->setNumberStart(1000)
            ->setNumberEnd(1999);

        $modules = $this->createStub(BuilderModuleRepository::class);
        $modules->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?BuilderModule => $criteria === ['code' => 'cadastros'] ? $module : null
        );

        $programs = $this->createStub(ProgramRepository::class);
        $programs->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Program => $criteria === ['code' => 'cd1001'] ? $existingProgram : null
        );

        $environment = $this->createStub(RuntimeEnvironmentIdentityResolver::class);
        $environment->method('resolve')->willReturn([
            'databaseIdentity' => 'db:test-1',
            'databaseEnvironment' => 'test',
        ]);

        return new ProgramBuilderService(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(BuilderApiSourceRepository::class),
            $this->createStub(BuilderEditorLockRepository::class),
            $modules,
            $this->createStub(BuilderFieldRepository::class),
            $this->createStub(BuilderEntityVersionRepository::class),
            $this->createStub(BuilderProgramVersionRepository::class),
            $programs,
            $this->createStub(ScreenDefinitionRepository::class),
            $this->createStub(RuntimeEndpointRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(ProgramGovernanceService::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(RuntimeNotificationService::class),
            $environment,
            $this->createStub(PermissionResolver::class),
            $this->createStub(RuntimeSessionGuard::class),
            $this->createStub(OdooClient::class),
            $this->createStub(RuntimeEventService::class),
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
