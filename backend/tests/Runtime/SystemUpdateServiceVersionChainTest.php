<?php

namespace App\Tests\Runtime;

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

        $executions = $this->createMock(SystemUpdateExecutionRepository::class);
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

        $executions = $this->createMock(SystemUpdateExecutionRepository::class);
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

        $executions = $this->createMock(SystemUpdateExecutionRepository::class);
        $executions->method('findLatestSuccessfulVersionBySubscriber')->willReturn('2.5.0');
        $executions->method('findSuccessfulVersionsBySubscriber')->willReturn(['2.5.0']);

        $activations = $this->createMock(SystemUpdateTenantActivationRepository::class);
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
        $activationsEnabled = $this->createMock(SystemUpdateTenantActivationRepository::class);
        $activationsEnabled->method('findLatestByVersionAndSubscriber')->willReturn($activation);

        $serviceEnabled = $this->service([$release], $executions, $activationsEnabled);
        $methodEnabled = new \ReflectionMethod($serviceEnabled, 'evaluateReleaseEntity');
        $methodEnabled->setAccessible(true);

        $enabled = $methodEnabled->invoke($serviceEnabled, $release, 'empresa-a');
        self::assertSame('pending', $enabled['status']);
    }

    /**
     * @param list<SystemUpdateRelease> $catalog
     */
    private function service(array $catalog, SystemUpdateExecutionRepository $executions, ?SystemUpdateTenantActivationRepository $activations = null): SystemUpdateService
    {
        $releases = $this->createMock(SystemUpdateReleaseRepository::class);
        $releases->method('findAllOrdered')->willReturn($catalog);

        $consents = $this->createMock(SystemUpdateConsentRepository::class);
        $consents->method('findLatestByVersionAndSubscriber')->willReturn(null);

        $tenantActivations = $activations ?: $this->createMock(SystemUpdateTenantActivationRepository::class);
        $tenantActivations->method('findLatestByVersionAndSubscriber')->willReturn(null);

        $environment = $this->createMock(RuntimeEnvironmentIdentityResolver::class);
        $environment->method('resolve')->willReturn([
            'databaseEnvironment' => 'prod',
            'databaseIdentity' => 'saas:test',
        ]);

        $deploymentMode = $this->createMock(DeploymentModeResolver::class);
        $deploymentMode->method('resolve')->willReturn('saas');

        $central = $this->createMock(CentralControlResolver::class);
        $central->method('isCentralControl')->willReturn(true);

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
            $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(AuthSubscriberRepository::class),
            $central,
            $this->createStub(SystemUpdateStepRunner::class),
            $this->createStub(SystemUpdatePackageDownloader::class),
            $this->createStub(SystemUpdateOrchestratorClient::class),
            new SystemUpdateManifestRulesValidator(),
        );
    }
}
