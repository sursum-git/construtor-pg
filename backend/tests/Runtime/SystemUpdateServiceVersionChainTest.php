<?php

namespace App\Tests\Runtime;

use App\Entity\AuthSubscriber;
use App\Entity\SystemUpdateRelease;
use App\Entity\SystemUpdateTenantActivation;
use App\Repository\AuthSubscriberRepository;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\RuntimeAsyncJobRepository;
use App\Repository\SystemUpdateConsentRepository;
use App\Repository\SystemUpdateExecutionRepository;
use App\Repository\SystemUpdateReleaseRepository;
use App\Repository\SystemUpdateTenantActivationRepository;
use App\Runtime\CentralControlResolver;
use App\Runtime\DeploymentModeResolver;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramOverlayService;
use App\Runtime\RuntimeAsyncJobService;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\SystemUpdateManifestLoader;
use App\Runtime\SystemUpdateManifestRulesValidator;
use App\Runtime\SystemUpdateOrchestratorClient;
use App\Runtime\SystemUpdatePackageDownloader;
use App\Runtime\SystemUpdateService;
use App\Runtime\SystemUpdateStepRunner;
use App\Runtime\SystemVersionResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SystemUpdateServiceVersionChainTest extends TestCase
{
    public function testBlocksReleaseForSubscriberWithoutRequiredAppliedUpdate(): void
    {
        $required = (new SystemUpdateRelease())->setVersion('2.3.5')->setTitle('Base estrutural')->setCategory('required_structural');
        $target = (new SystemUpdateRelease())
            ->setVersion('2.4.0')
            ->setTitle('Nova release')
            ->setCategory('required_structural')
            ->setRequiresVersionMin('2.3.0')
            ->setRequiresAppliedUpdates(['2.3.5']);

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturnMap([
            ['empresa-a', '2.3.0'],
            ['empresa-b', '2.3.0'],
        ]);
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturnCallback(function (?string $subscriberCode): array {
            return $subscriberCode === 'empresa-b' ? ['2.3.5'] : [];
        });

        $service = $this->service([$required, $target], $executions);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $blocked = $method->invoke($service, $target, 'empresa-a');
        $allowed = $method->invoke($service, $target, 'empresa-b');

        self::assertSame('blocked_dependency', $blocked['status']);
        self::assertSame('pending', $allowed['status']);
    }

    public function testReplacedVersionSatisfiesFutureDependency(): void
    {
        $base = (new SystemUpdateRelease())
            ->setVersion('2.3.5')
            ->setTitle('Base estrutural')
            ->setCategory('required_structural');
        $replacement = (new SystemUpdateRelease())
            ->setVersion('2.4.0')
            ->setTitle('Release substituta')
            ->setCategory('required_structural')
            ->setReplaces(['2.3.5']);
        $future = (new SystemUpdateRelease())
            ->setVersion('2.5.0')
            ->setTitle('Release futura')
            ->setCategory('required_structural')
            ->setRequiresAppliedUpdates(['2.3.5']);

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.4.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.4.0']);

        $service = $this->service([$base, $replacement, $future], $executions);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $evaluation = $method->invoke($service, $future, 'empresa-a');

        self::assertSame('pending', $evaluation['status']);
        self::assertSame(['2.3.5'], $evaluation['requiresAppliedUpdates']);
    }

    public function testOptionalReleaseRequiresTenantActivationInSaas(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('2.6.0')
            ->setTitle('Melhoria visual')
            ->setCategory('optional_visual');

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.5.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.5.0']);

        $activations = $this->createStub(SystemUpdateTenantActivationRepository::class);
        $activations->method('findLatestByVersionAndSubscriber')->willReturn(null);

        $service = $this->service([$release], $executions, $activations);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $waiting = $method->invoke($service, $release, 'empresa-a');
        self::assertSame('awaiting_tenant_activation', $waiting['status']);

        $activation = (new SystemUpdateTenantActivation())
            ->setReleaseVersion('2.6.0')
            ->setStatus('enabled')
            ->setTargetSubscriberCode('empresa-a');
        $activationsEnabled = $this->createStub(SystemUpdateTenantActivationRepository::class);
        $activationsEnabled->method('findLatestByVersionAndSubscriber')->willReturn($activation);

        $serviceEnabled = $this->service([$release], $executions, $activationsEnabled);
        $methodEnabled = new \ReflectionMethod($serviceEnabled, 'evaluateReleaseEntity');
        $methodEnabled->setAccessible(true);

        $enabled = $methodEnabled->invoke($serviceEnabled, $release, 'empresa-a');
        self::assertSame('pending', $enabled['status']);
    }

    public function testReleaseOutsideSubscriberChannelIsBlocked(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('2.7.0')
            ->setTitle('Canario')
            ->setCategory('optional_visual')
            ->setMetadata([
                'channels' => ['canary'],
            ]);

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.5.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.5.0']);

        $service = $this->service([$release], $executions);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $evaluation = $method->invoke($service, $release, 'empresa-a');

        self::assertSame('channel_unavailable', $evaluation['status']);
    }

    public function testSharedRuntimeOptionalReleaseRequiresRuntimeActivationForAllSubscribers(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('2.8.0')
            ->setTitle('Melhoria compartilhada')
            ->setCategory('optional_visual');

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.7.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.7.0']);

        $subscriberA = (new AuthSubscriber())
            ->setCode('empresa-a')
            ->setName('Empresa A')
            ->setMetadata([
                'deployment' => [
                    'mode' => 'shared_program_shared_db',
                    'runtimeEnvironmentCode' => 'runtime-compartilhado',
                ],
                'provisioning' => [
                    'updateChannel' => 'stable',
                ],
            ]);
        $subscriberB = (new AuthSubscriber())
            ->setCode('empresa-b')
            ->setName('Empresa B')
            ->setMetadata([
                'deployment' => [
                    'mode' => 'shared_program_shared_db',
                    'runtimeEnvironmentCode' => 'runtime-compartilhado',
                ],
                'provisioning' => [
                    'updateChannel' => 'stable',
                ],
            ]);

        $activationA = (new SystemUpdateTenantActivation())
            ->setReleaseVersion('2.8.0')
            ->setTargetSubscriberCode('empresa-a')
            ->setStatus('enabled');

        $activations = $this->createStub(SystemUpdateTenantActivationRepository::class);
        $activations->method('findLatestByVersionAndSubscriber')->willReturnCallback(
            static function (string $version, string $subscriberCode) use ($activationA) {
                if ($version === '2.8.0' && $subscriberCode === 'empresa-a') {
                    return $activationA;
                }

                return null;
            }
        );

        $service = $this->service([$release], $executions, $activations, [$subscriberA, $subscriberB]);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $waiting = $method->invoke($service, $release, 'empresa-a');
        self::assertSame('awaiting_runtime_activation', $waiting['status']);
        self::assertSame('runtime_environment', $waiting['deploymentRule']['applyScope']);
        self::assertFalse($waiting['deploymentRule']['supportsPerTenantActivation']);
        self::assertSame(2, $waiting['deploymentRule']['sharedRuntimeSubscriberCount']);

        $activationB = (new SystemUpdateTenantActivation())
            ->setReleaseVersion('2.8.0')
            ->setTargetSubscriberCode('empresa-b')
            ->setStatus('enabled');
        $activationsEnabled = $this->createStub(SystemUpdateTenantActivationRepository::class);
        $activationsEnabled->method('findLatestByVersionAndSubscriber')->willReturnCallback(
            static function (string $version, string $subscriberCode) use ($activationA, $activationB) {
                if ($version !== '2.8.0') {
                    return null;
                }

                return match ($subscriberCode) {
                    'empresa-a' => $activationA,
                    'empresa-b' => $activationB,
                    default => null,
                };
            }
        );

        $serviceEnabled = $this->service([$release], $executions, $activationsEnabled, [$subscriberA, $subscriberB]);
        $methodEnabled = new \ReflectionMethod($serviceEnabled, 'evaluateReleaseEntity');
        $methodEnabled->setAccessible(true);
        $enabled = $methodEnabled->invoke($serviceEnabled, $release, 'empresa-a');

        self::assertSame('pending', $enabled['status']);
    }

    public function testDeploymentRuleUsesSubscriberDatabaseScopeForSharedProgramDedicatedDb(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('2.9.0')
            ->setTitle('Release dedicada')
            ->setCategory('required_structural');

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.8.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.8.0']);

        $subscriber = (new AuthSubscriber())
            ->setCode('empresa-db')
            ->setName('Empresa DB')
            ->setMetadata([
                'deployment' => [
                    'mode' => 'shared_program_dedicated_db',
                    'runtimeEnvironmentCode' => 'runtime-compartilhado',
                ],
                'provisioning' => [
                    'updateChannel' => 'stable',
                ],
            ]);

        $service = $this->service([$release], $executions, null, [$subscriber]);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $evaluation = $method->invoke($service, $release, 'empresa-db');

        self::assertSame('subscriber_database', $evaluation['deploymentRule']['applyScope']);
        self::assertSame('shared_application', $evaluation['deploymentRule']['rolloutScope']);
        self::assertSame('runtime_environment', $evaluation['deploymentRule']['consentScope']);
        self::assertFalse($evaluation['deploymentRule']['requiresSharedRuntimeCoordination']);
        self::assertFalse($evaluation['deploymentRule']['supportsPerTenantActivation']);
    }

    public function testNormalizeReleaseAppliesDefaultPolicyByCategory(): void
    {
        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('1.0.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['1.0.0']);

        $service = $this->service([], $executions);
        $method = new \ReflectionMethod($service, 'normalizeRelease');
        $method->setAccessible(true);

        $normalized = $method->invoke($service, [
            'version' => '3.0.0',
            'title' => 'Estrutural',
            'category' => 'required_structural',
        ], 'test', 'hash');

        self::assertTrue($normalized['autoApplySaas']);
        self::assertFalse($normalized['autoApplyOnPrem']);
        self::assertTrue($normalized['requiresSubscriberConsent']);
        self::assertTrue($normalized['blocksNextUpdates']);
        self::assertTrue($normalized['metadata']['requiresBackup']);
        self::assertTrue($normalized['metadata']['requiresMaintenanceMode']);
    }

    public function testHydrateAndSerializePreserveApplicationPolicy(): void
    {
        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('1.0.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['1.0.0']);

        $service = $this->service([], $executions);
        $normalize = new \ReflectionMethod($service, 'normalizeRelease');
        $normalize->setAccessible(true);
        $hydrate = new \ReflectionMethod($service, 'hydrateRelease');
        $hydrate->setAccessible(true);
        $serialize = new \ReflectionMethod($service, 'releaseToArray');
        $serialize->setAccessible(true);

        $normalized = $normalize->invoke($service, [
            'version' => '3.1.0',
            'title' => 'Visual pilot',
            'category' => 'optional_visual',
            'metadata' => [
                'applicationPolicyOverride' => true,
                'applicationPolicyOverrideJustification' => 'Cliente piloto com aceite expresso.',
            ],
            'autoApplySaas' => true,
        ], 'test', 'hash');

        $release = new SystemUpdateRelease();
        $hydrate->invoke($service, $release, $normalized);
        $serialized = $serialize->invoke($service, $release);

        self::assertTrue($serialized['autoApplySaas']);
        self::assertFalse($serialized['autoApplyOnPrem']);
        self::assertTrue($serialized['requiresSubscriberConsent']);
        self::assertFalse($serialized['blocksNextUpdates']);
        self::assertTrue($serialized['metadata']['applicationPolicyOverride']);
        self::assertSame('Cliente piloto com aceite expresso.', $serialized['metadata']['applicationPolicyOverrideJustification']);
    }

    public function testDetectsStandardProgramInstallationNeed(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('3.0.0')
            ->setTitle('Programa padrao novo')
            ->setCategory('recommended')
            ->setProgramUpdates([
                [
                    'programCode' => 'admin.novo-programa',
                    'targetPublishedVersion' => '1.0.0',
                    'policy' => 'respect_customizations',
                ],
            ]);

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.9.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.9.0']);

        $programVersions = $this->createStub(BuilderProgramVersionRepository::class);
        $programVersions->method('findPublishedStandardByProgramCode')->willReturn(null);
        $programVersions->method('findPublishedByProgramCode')->willReturn(null);

        $service = $this->service([$release], $executions, null, [], $programVersions);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $evaluation = $method->invoke($service, $release, 'empresa-a');
        $program = $evaluation['impactReport']['programs'][0] ?? [];

        self::assertSame('install_new_standard', $program['standardProgramStatus'] ?? null);
        self::assertSame('install', $program['standardProgramAction'] ?? null);
    }

    public function testDetectsStandardProgramVersionUpgradeNeed(): void
    {
        $release = (new SystemUpdateRelease())
            ->setVersion('3.1.0')
            ->setTitle('Programa padrao atualizado')
            ->setCategory('recommended')
            ->setProgramUpdates([
                [
                    'programCode' => 'admin.integracoes',
                    'targetPublishedVersion' => '1.1.0',
                    'policy' => 'respect_customizations',
                ],
            ]);

        $executions = $this->createStub(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('3.0.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['3.0.0']);

        $published = (new \App\Entity\BuilderProgramVersion())
            ->setProgramCode('admin.integracoes')
            ->setProgramTitle('Integracoes')
            ->setStatus('published')
            ->setVersion('1.0.0')
            ->setProgramOrigin('standard');

        $programVersions = $this->createStub(BuilderProgramVersionRepository::class);
        $programVersions->method('findPublishedStandardByProgramCode')->willReturn($published);
        $programVersions->method('findPublishedByProgramCode')->willReturn($published);

        $service = $this->service([$release], $executions, null, [], $programVersions);
        $method = new \ReflectionMethod($service, 'evaluateReleaseEntity');
        $method->setAccessible(true);

        $evaluation = $method->invoke($service, $release, 'empresa-a');
        $program = $evaluation['impactReport']['programs'][0] ?? [];

        self::assertSame('update_standard', $program['standardProgramStatus'] ?? null);
        self::assertSame('update', $program['standardProgramAction'] ?? null);
    }

    /**
     * @param list<SystemUpdateRelease> $catalog
     */
    private function service(array $catalog, SystemUpdateExecutionRepository $executions, ?SystemUpdateTenantActivationRepository $activations = null, array $subscribers = [], ?BuilderProgramVersionRepository $programVersions = null): SystemUpdateService
    {
        if ($subscribers === []) {
            $subscribers = [
                (new AuthSubscriber())
                    ->setCode('empresa-a')
                    ->setName('Empresa A')
                    ->setMetadata([
                        'deployment' => [
                            'mode' => 'dedicated_stack',
                            'runtimeEnvironmentCode' => 'runtime-a',
                        ],
                        'provisioning' => [
                            'updateChannel' => 'stable',
                        ],
                    ]),
                (new AuthSubscriber())
                    ->setCode('empresa-b')
                    ->setName('Empresa B')
                    ->setMetadata([
                        'deployment' => [
                            'mode' => 'dedicated_stack',
                            'runtimeEnvironmentCode' => 'runtime-b',
                        ],
                        'provisioning' => [
                            'updateChannel' => 'stable',
                        ],
                    ]),
            ];
        }

        $releases = $this->createStub(SystemUpdateReleaseRepository::class);
        $releases->method('findAllOrdered')->willReturn($catalog);

        $consents = $this->createStub(SystemUpdateConsentRepository::class);
        $consents->method('findLatestByVersionAndSubscriber')->willReturn(null);

        $tenantActivations = $activations ?: $this->createStub(SystemUpdateTenantActivationRepository::class);
        $tenantActivations->method('findLatestByVersionAndSubscriber')->willReturn(null);

        $programVersionsRepository = $programVersions ?: $this->createStub(BuilderProgramVersionRepository::class);
        $programVersionsRepository->method('findPublishedStandardByProgramCode')->willReturn(null);
        $programVersionsRepository->method('findPublishedByProgramCode')->willReturn(null);

        $environment = $this->createStub(RuntimeEnvironmentIdentityResolver::class);
        $environment->method('resolve')->willReturn([
            'databaseEnvironment' => 'prod',
            'databaseIdentity' => 'saas:test',
        ]);

        $deploymentMode = $this->createStub(DeploymentModeResolver::class);
        $deploymentMode->method('resolve')->willReturn('saas');

        $central = $this->createStub(CentralControlResolver::class);
        $central->method('isCentralControl')->willReturn(true);

        $subscriberRepository = $this->createStub(AuthSubscriberRepository::class);
        $subscriberRepository->method('findEnabledByCode')->willReturnCallback(
            static function (string $code) use ($subscribers): ?AuthSubscriber {
                foreach ($subscribers as $subscriber) {
                    if ($subscriber instanceof AuthSubscriber && $subscriber->getCode() === $code && $subscriber->isEnabled()) {
                        return $subscriber;
                    }
                }

                return null;
            }
        );
        $subscriberRepository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($subscribers): ?AuthSubscriber {
                $code = (string) ($criteria['code'] ?? '');
                foreach ($subscribers as $subscriber) {
                    if ($subscriber instanceof AuthSubscriber && $subscriber->getCode() === $code) {
                        return $subscriber;
                    }
                }

                return null;
            }
        );
        $subscriberRepository->method('findEnabledOrdered')->willReturn(
            array_values(array_filter($subscribers, static fn ($subscriber): bool => $subscriber instanceof AuthSubscriber && $subscriber->isEnabled()))
        );

        return new SystemUpdateService(
            $this->createStub(SystemUpdateManifestLoader::class),
            $releases,
            $consents,
            $tenantActivations,
            $executions,
            $this->createStub(RuntimeAsyncJobService::class),
            $this->createStub(RuntimeAsyncJobRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $environment,
            $deploymentMode,
            $this->createStub(SystemVersionResolver::class),
            $this->createStub(PermissionResolver::class),
            $this->createStub(ProgramOverlayService::class),
            $this->createStub(BuilderProgramOverlayRepository::class),
            $this->createStub(BuilderProgramOverlayVersionRepository::class),
            $programVersionsRepository,
            $subscriberRepository,
            $central,
            $this->createStub(SystemUpdateStepRunner::class),
            $this->createStub(SystemUpdatePackageDownloader::class),
            $this->createStub(SystemUpdateOrchestratorClient::class),
            new SystemUpdateManifestRulesValidator(),
        );
    }
}
