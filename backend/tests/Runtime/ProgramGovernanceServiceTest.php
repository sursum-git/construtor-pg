<?php

namespace App\Tests\Runtime;

use App\Entity\BuilderEditorLock;
use App\Entity\BuilderProgramVersion;
use App\Entity\ProgramChangeGrant;
use App\Entity\ProgramChangeRequest;
use App\Entity\ProgramPublicationApproval;
use App\Entity\ProgramTestExecution;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderProgramOverlayRepository;
use App\Repository\BuilderProgramOverlayVersionRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramChangeGrantRepository;
use App\Repository\ProgramChangeRequestRepository;
use App\Repository\ProgramPublicationApprovalRepository;
use App\Repository\ProgramTestExecutionRepository;
use App\Repository\SystemRecordIntegrityRepository;
use App\Runtime\GovernanceRetentionPolicyService;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProgramGovernanceServiceTest extends TestCase
{
    public function testStandardDraftRequiresActiveGrant(): void
    {
        $grants = $this->createMock(ProgramChangeGrantRepository::class);
        $grants->expects(self::once())
            ->method('findActiveForUser')
            ->with('cd0001', 'cliente', 'user-1')
            ->willReturn(null);

        $service = $this->createService(
            grants: $grants,
        );

        $version = (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setBuilderEntityCode('cliente')
            ->setProgramOrigin('standard')
            ->setOwnerScope('system');

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Programa padrao exige autorizacao ativa para edicao.');

        $service->assertCanEditProgramDraft($version);
    }

    public function testStandardPublishRequiresApproval(): void
    {
        [$grant, $lock, $entityLock] = $this->activeGrantAndLocks();

        $grants = $this->createMock(ProgramChangeGrantRepository::class);
        $grants->expects(self::once())
            ->method('findActiveForUser')
            ->willReturn($grant);

        $locks = $this->createMock(BuilderEditorLockRepository::class);
        $locks->expects(self::exactly(2))
            ->method('findActiveByScope')
            ->willReturnOnConsecutiveCalls($lock, $entityLock);

        $approvals = $this->createMock(ProgramPublicationApprovalRepository::class);
        $approvals->expects(self::once())
            ->method('findActiveApproval')
            ->with('cd0001', 55)
            ->willReturn(null);

        $service = $this->createService(
            grants: $grants,
            approvals: $approvals,
            locks: $locks,
        );

        $version = $this->standardVersion();
        $this->setEntityId($version, 55);

        try {
            $service->assertCanPublish($version);
            self::fail('Expected approval requirement.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROGRAM_PUBLICATION_APPROVAL_REQUIRED', $error->getErrorCode());
        }
    }

    public function testStandardPublishRequiresPassedTests(): void
    {
        [$grant, $lock, $entityLock] = $this->activeGrantAndLocks();

        $grants = $this->createMock(ProgramChangeGrantRepository::class);
        $grants->expects(self::once())
            ->method('findActiveForUser')
            ->willReturn($grant);

        $locks = $this->createMock(BuilderEditorLockRepository::class);
        $locks->expects(self::exactly(2))
            ->method('findActiveByScope')
            ->willReturnOnConsecutiveCalls($lock, $entityLock);

        $approval = (new ProgramPublicationApproval())
            ->setProgramCode('cd0001')
            ->setBuilderProgramVersionId(55)
            ->setStatus('approved')
            ->setTestExecutionBundleId('bundle-a');

        $test = (new ProgramTestExecution())
            ->setProgramCode('cd0001')
            ->setBuilderProgramVersionId(55)
            ->setBundleId('bundle-a')
            ->setTestPlanId('roteiro-web')
            ->setExecutedBy('user-1')
            ->setStatus('failed')
            ->setChecklistSnapshot([]);

        $approvals = $this->createMock(ProgramPublicationApprovalRepository::class);
        $approvals->expects(self::once())
            ->method('findActiveApproval')
            ->willReturn($approval);

        $tests = $this->createMock(ProgramTestExecutionRepository::class);
        $tests->expects(self::once())
            ->method('findByBundle')
            ->with('cd0001', 55, 'bundle-a')
            ->willReturn([$test]);

        $service = $this->createService(
            grants: $grants,
            approvals: $approvals,
            tests: $tests,
            locks: $locks,
        );

        $version = $this->standardVersion();
        $this->setEntityId($version, 55);

        try {
            $service->assertCanPublish($version);
            self::fail('Expected failed test bundle.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('PROGRAM_PUBLICATION_TESTS_NOT_PASSED', $error->getErrorCode());
        }
    }

    public function testChangeGrantStatusReleasesLinkedLocks(): void
    {
        $request = (new ProgramChangeRequest())
            ->setRequestCode('REQ-1')
            ->setProgramCode('cd0001')
            ->setRequestedBy('user-1')
            ->setRequestedActions(['edit']);

        $grant = (new ProgramChangeGrant())
            ->setRequest($request)
            ->setProgramCode('cd0001')
            ->setGrantedToUserId('user-1')
            ->setAllowedActions(['edit'])
            ->setStatus('active');
        $this->setEntityId($grant, 17);

        $lock = (new BuilderEditorLock())
            ->setScopeType('program')
            ->setScopeCode('cd0001')
            ->setTenantId('tenant-a')
            ->setUserId('user-1')
            ->setSessionId('sess-1')
            ->setLockToken('abc')
            ->setGrantId(17)
            ->heartbeat(60);

        $grants = $this->createMock(ProgramChangeGrantRepository::class);
        $grants->expects(self::once())
            ->method('find')
            ->with(17)
            ->willReturn($grant);

        $locks = $this->createMock(BuilderEditorLockRepository::class);
        $locks->expects(self::once())
            ->method('findBy')
            ->with(['grantId' => 17, 'status' => 'active'])
            ->willReturn([$lock]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist');

        $service = $this->createService(
            grants: $grants,
            locks: $locks,
            entityManager: $entityManager,
        );

        $changed = $service->changeGrantStatus(17, 'frozen');

        self::assertSame('frozen', $changed->getStatus());
        self::assertSame('released', $lock->getStatus());
    }

    private function standardVersion(): BuilderProgramVersion
    {
        return (new BuilderProgramVersion())
            ->setProgramCode('cd0001')
            ->setBuilderEntityCode('cliente')
            ->setProgramOrigin('standard')
            ->setOwnerScope('system');
    }

    /**
     * @return array{0: ProgramChangeGrant, 1: BuilderEditorLock, 2: BuilderEditorLock}
     */
    private function activeGrantAndLocks(): array
    {
        $request = (new ProgramChangeRequest())
            ->setRequestCode('REQ-1')
            ->setProgramCode('cd0001')
            ->setRequestedBy('user-1')
            ->setRequestedActions(['edit', 'publish']);

        $grant = (new ProgramChangeGrant())
            ->setRequest($request)
            ->setProgramCode('cd0001')
            ->setBuilderEntityCode('cliente')
            ->setGrantedToUserId('user-1')
            ->setAllowedActions(['edit', 'publish'])
            ->setStatus('active');
        $this->setEntityId($grant, 17);

        $lock = (new BuilderEditorLock())
            ->setScopeType('program')
            ->setScopeCode('cd0001')
            ->setTenantId('tenant-a')
            ->setUserId('user-1')
            ->setSessionId('sess-1')
            ->setLockToken('abc')
            ->setGrantId(17)
            ->heartbeat(60);

        $entityLock = (new BuilderEditorLock())
            ->setScopeType('entity')
            ->setScopeCode('cliente')
            ->setTenantId('tenant-a')
            ->setUserId('user-1')
            ->setSessionId('sess-1')
            ->setLockToken('def')
            ->setGrantId(17)
            ->heartbeat(60);

        return [$grant, $lock, $entityLock];
    }

    private function permissionResolver(): PermissionResolver
    {
        $resolver = $this->createMock(PermissionResolver::class);
        $resolver->method('getUserId')->willReturn('user-1');
        $resolver->method('getTenantId')->willReturn('tenant-a');
        $resolver->method('getSessionId')->willReturn('sess-1');
        return $resolver;
    }

    private function createService(
        ?ProgramChangeGrantRepository $grants = null,
        ?ProgramChangeRequestRepository $requests = null,
        ?ProgramPublicationApprovalRepository $approvals = null,
        ?ProgramTestExecutionRepository $tests = null,
        ?BuilderEditorLockRepository $locks = null,
        ?BuilderProgramVersionRepository $versions = null,
        ?EntityManagerInterface $entityManager = null,
    ): ProgramGovernanceService {
        return new ProgramGovernanceService(
            $grants ?? $this->createStub(ProgramChangeGrantRepository::class),
            $requests ?? $this->createStub(ProgramChangeRequestRepository::class),
            $approvals ?? $this->createStub(ProgramPublicationApprovalRepository::class),
            $tests ?? $this->createStub(ProgramTestExecutionRepository::class),
            $locks ?? $this->createStub(BuilderEditorLockRepository::class),
            $versions ?? $this->createStub(BuilderProgramVersionRepository::class),
            $this->createStub(BuilderProgramOverlayRepository::class),
            $this->createStub(BuilderProgramOverlayVersionRepository::class),
            $this->createStub(SystemRecordIntegrityRepository::class),
            $this->permissionResolver(),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(RuntimeNotificationService::class),
            $this->createStub(GovernanceRetentionPolicyService::class),
            $this->createStub(StructuralIntegrityService::class),
            $this->createStub(Connection::class),
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
