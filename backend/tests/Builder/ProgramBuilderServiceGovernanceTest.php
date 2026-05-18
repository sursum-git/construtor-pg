<?php

namespace App\Tests\Builder;

use App\Builder\ProgramBuilderService;
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
        );
    }
}
