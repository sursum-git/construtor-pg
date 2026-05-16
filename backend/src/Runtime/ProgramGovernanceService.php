<?php

namespace App\Runtime;

use App\Entity\BuilderEditorLock;
use App\Entity\BuilderProgramVersion;
use App\Entity\ProgramPublicationApproval;
use App\Entity\ProgramTestExecution;
use App\Entity\ProgramChangeGrant;
use App\Entity\ProgramChangeRequest;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramChangeGrantRepository;
use App\Repository\ProgramChangeRequestRepository;
use App\Repository\ProgramPublicationApprovalRepository;
use App\Repository\ProgramTestExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProgramGovernanceService
{
    public function __construct(
        private readonly ProgramChangeGrantRepository $grants,
        private readonly ProgramChangeRequestRepository $requests,
        private readonly ProgramPublicationApprovalRepository $approvals,
        private readonly ProgramTestExecutionRepository $tests,
        private readonly BuilderEditorLockRepository $editorLocks,
        private readonly BuilderProgramVersionRepository $versions,
        private readonly PermissionResolver $permissions,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeNotificationService $notifications,
        private readonly GovernanceRetentionPolicyService $retentionPolicy,
    ) {
    }

    public function createChangeRequest(string $programCode, ?string $builderEntityCode, array $requestedActions, ?string $reason): ProgramChangeRequest
    {
        $request = (new ProgramChangeRequest())
            ->setRequestCode($this->generateRequestCode($programCode))
            ->setProgramCode($programCode)
            ->setBuilderEntityCode($builderEntityCode)
            ->setRequestedBy($this->permissions->getUserId())
            ->setRequestedActions($requestedActions)
            ->setReason($reason)
            ->setStatus('pending')
            ->setMetadata([
                'tenantId' => $this->permissions->getTenantId(),
                'sessionId' => $this->permissions->getSessionId(),
            ]);
        $this->entityManager->persist($request);
        $this->notifications->createAdministrativeNotification(
            'Solicitacao de alteracao pendente',
            sprintf('Foi aberta uma solicitacao de alteracao para o programa %s.', $programCode),
            [
                'code' => 'governanca.request.pending.' . strtolower($programCode) . '.' . strtolower($request->getRequestCode()),
                'category' => 'governanca',
                'severity' => 'info',
                'actionRequired' => true,
                'targetGroups' => ['admin'],
                'targetUserIds' => [$request->getRequestedBy()],
                'linkScreenId' => 'admin.programa-grants-operacao',
                'metadata' => [
                    'actionLabel' => 'Abrir governanca',
                    'requestCode' => $request->getRequestCode(),
                    'programCode' => $programCode,
                    'builderEntityCode' => $builderEntityCode,
                    'requestedActions' => $requestedActions,
                    'actionQuery' => [
                        'programCode' => $programCode,
                        'focusRequestCode' => $request->getRequestCode(),
                        'tab' => 'requests',
                        'actionSuggestion' => 'Revisar a solicitacao e decidir sobre a liberacao do grant.',
                    ],
                ],
            ]
        );
        return $request;
    }

    public function createGrant(ProgramChangeRequest $request, string $grantedToUserId, array $allowedActions): ProgramChangeGrant
    {
        $request
            ->setStatus('approved')
            ->setApprovedBy($this->permissions->getUserId())
            ->setApprovedAt(new \DateTimeImmutable());
        $grant = (new ProgramChangeGrant())
            ->setRequest($request)
            ->setProgramCode($request->getProgramCode())
            ->setBuilderEntityCode($request->getBuilderEntityCode())
            ->setGrantedToUserId($grantedToUserId)
            ->setAllowedActions($allowedActions)
            ->setStatus('active')
            ->setValidUntilPublish(true)
            ->setMetadata([
                'approvedBy' => $this->permissions->getUserId(),
                'tenantId' => $this->permissions->getTenantId(),
            ]);
        $this->entityManager->persist($request);
        $this->entityManager->persist($grant);
        $this->notifications->createAdministrativeNotification(
            'Grant liberado',
            sprintf('O grant para o programa %s foi liberado para %s.', $request->getProgramCode(), $grantedToUserId),
            [
                'code' => 'governanca.grant.active.' . strtolower($request->getProgramCode()) . '.' . strtolower($request->getRequestCode()),
                'category' => 'governanca',
                'severity' => 'info',
                'actionRequired' => true,
                'targetUserIds' => array_values(array_unique([$grantedToUserId, $request->getRequestedBy()])),
                'linkScreenId' => 'admin.programa-grants-operacao',
                'metadata' => [
                    'actionLabel' => 'Abrir governanca',
                    'grantId' => $grant->getId(),
                    'requestCode' => $request->getRequestCode(),
                    'programCode' => $request->getProgramCode(),
                    'allowedActions' => $allowedActions,
                    'actionQuery' => [
                        'programCode' => $request->getProgramCode(),
                        'focusGrantId' => (string) $grant->getId(),
                        'focusRequestCode' => $request->getRequestCode(),
                        'tab' => 'grants',
                        'actionSuggestion' => 'Validar o grant liberado e decidir se o editor pode seguir.',
                    ],
                ],
            ]
        );
        return $grant;
    }

    public function changeGrantStatus(int $grantId, string $status): ProgramChangeGrant
    {
        $grant = $this->grants->find($grantId);
        if (!$grant) {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_NOT_FOUND', 'Grant de programa nao encontrado.', 404, ['grantId' => $grantId]);
        }
        if (!in_array($status, ['active', 'frozen', 'revoked'], true)) {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_STATUS_INVALID', 'Status do grant invalido.', 422, ['status' => $status]);
        }

        $grant->setStatus($status);
        $metadata = $grant->getMetadata();
        $metadata['lastStatusChangedBy'] = $this->permissions->getUserId();
        $metadata['lastStatusChangedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $grant->setMetadata($metadata);
        $this->entityManager->persist($grant);
        $this->releaseLocksForGrant($grantId);
        if (in_array($status, ['frozen', 'revoked'], true)) {
            $this->notifications->createAdministrativeNotification(
                $status === 'frozen' ? 'Grant congelado' : 'Grant revogado',
                sprintf('O grant do programa %s foi marcado como %s.', $grant->getProgramCode(), $status === 'frozen' ? 'congelado' : 'revogado'),
                [
                    'code' => 'governanca.grant.' . $status . '.' . $grantId,
                    'category' => 'governanca',
                    'severity' => $status === 'revoked' ? 'error' : 'warning',
                    'actionRequired' => true,
                    'targetUserIds' => [$grant->getGrantedToUserId()],
                    'linkScreenId' => 'admin.programa-grants-operacao',
                    'metadata' => [
                        'actionLabel' => 'Abrir governanca',
                        'grantId' => $grantId,
                        'programCode' => $grant->getProgramCode(),
                        'status' => $status,
                        'actionQuery' => [
                            'programCode' => $grant->getProgramCode(),
                            'focusGrantId' => (string) $grantId,
                            'tab' => 'grants',
                            'actionSuggestion' => $status === 'frozen'
                                ? 'O grant foi congelado. Revisar se pode ser reativado ou se o fluxo deve permanecer suspenso.'
                                : 'O grant foi revogado. Revisar o motivo e decidir se um novo grant deve ser criado.',
                        ],
                    ],
                ]
            );
        }

        return $grant;
    }

    public function registerTestExecution(BuilderProgramVersion $version, string $bundleId, string $testPlanId, string $status, array $checklist, array $evidences = [], ?string $notes = null): ProgramTestExecution
    {
        $test = (new ProgramTestExecution())
            ->setProgramCode($version->getProgramCode())
            ->setBuilderProgramVersionId((int) $version->getId())
            ->setBuilderEntityVersionId(null)
            ->setBundleId($bundleId)
            ->setTestPlanId($testPlanId)
            ->setExecutedBy($this->permissions->getUserId())
            ->setStatus($status)
            ->setChecklistSnapshot($checklist)
            ->setEvidences($evidences)
            ->setNotes($notes)
            ->setExecutedAt(new \DateTimeImmutable());
        $this->entityManager->persist($test);
        return $test;
    }

    public function approvePublication(BuilderProgramVersion $version, string $bundleId): ProgramPublicationApproval
    {
        $approval = (new ProgramPublicationApproval())
            ->setProgramCode($version->getProgramCode())
            ->setBuilderProgramVersionId((int) $version->getId())
            ->setRequestedBy($this->permissions->getUserId())
            ->setApprovedBy($this->permissions->getUserId())
            ->setStatus('approved')
            ->setTestExecutionBundleId($bundleId)
            ->setApprovedAt(new \DateTimeImmutable())
            ->setMetadata([
                'tenantId' => $this->permissions->getTenantId(),
                'sessionId' => $this->permissions->getSessionId(),
            ]);
        $this->entityManager->persist($approval);
        $this->notifications->createAdministrativeNotification(
            'Aprovacao final registrada',
            sprintf('A publicacao da versao %s do programa %s recebeu aprovacao final.', $version->getVersion(), $version->getProgramCode()),
            [
                'code' => 'governanca.approval.approved.' . strtolower($version->getProgramCode()) . '.' . (int) $version->getId(),
                'category' => 'governanca',
                'severity' => 'info',
                'actionRequired' => false,
                'targetUserIds' => [$approval->getRequestedBy()],
                'linkScreenId' => 'admin.programa-aprovacoes-operacao',
                'metadata' => [
                    'actionLabel' => 'Abrir governanca',
                    'approvalId' => $approval->getId(),
                    'programCode' => $version->getProgramCode(),
                    'builderProgramVersionId' => $version->getId(),
                    'testExecutionBundleId' => $bundleId,
                    'actionQuery' => [
                        'programCode' => $version->getProgramCode(),
                        'builderProgramVersionId' => (string) $version->getId(),
                        'focusApprovalId' => (string) $approval->getId(),
                        'focusBundleId' => $bundleId,
                        'tab' => 'approvals',
                        'actionSuggestion' => 'Validar o bundle aprovado e seguir para a publicacao governada.',
                    ],
                ],
            ]
        );
        return $approval;
    }

    public function assertCanEditProgramDraft(BuilderProgramVersion $version, ?string $builderEntityCode = null): ?ProgramChangeGrant
    {
        if (!$this->isStandardProgram($version->getProgramOrigin(), $version->getOwnerScope())) {
            return null;
        }

        $grant = $this->requireActiveGrant($version->getProgramCode(), $builderEntityCode ?: $version->getBuilderEntityCode());
        $this->requireActiveLockWithGrant('program', $version->getProgramCode(), $grant);
        if (($builderEntityCode ?: $version->getBuilderEntityCode()) !== '') {
            $this->requireActiveLockWithGrant('entity', (string) ($builderEntityCode ?: $version->getBuilderEntityCode()), $grant);
        }

        return $grant;
    }

    public function assertCanEditEntity(string $builderEntityCode): ?ProgramChangeGrant
    {
        $version = $this->findStandardProgramVersionByEntity($builderEntityCode);
        if (!$version) {
            return null;
        }

        $grant = $this->requireActiveGrant($version->getProgramCode(), $builderEntityCode);
        $this->requireActiveLockWithGrant('program', $version->getProgramCode(), $grant);
        $this->requireActiveLockWithGrant('entity', $builderEntityCode, $grant);

        return $grant;
    }

    public function assertCanPublish(BuilderProgramVersion $version): ProgramChangeGrant
    {
        if (!$this->isStandardProgram($version->getProgramOrigin(), $version->getOwnerScope())) {
            throw new RuntimeHttpException('PROGRAM_PUBLISH_POLICY_INVALID', 'A publicacao governada so se aplica ao programa padrao.', 422, [
                'programCode' => $version->getProgramCode(),
                'programOrigin' => $version->getProgramOrigin(),
                'ownerScope' => $version->getOwnerScope(),
            ]);
        }

        $grant = $this->assertCanEditProgramDraft($version);
        $approval = $this->approvals->findActiveApproval($version->getProgramCode(), (int) $version->getId());
        if (!$approval) {
            throw new RuntimeHttpException('PROGRAM_PUBLICATION_APPROVAL_REQUIRED', 'A publicacao exige aprovacao final ativa.', 422, [
                'programCode' => $version->getProgramCode(),
                'builderProgramVersionId' => $version->getId(),
            ]);
        }

        $bundleId = trim((string) ($approval->getTestExecutionBundleId() ?? ''));
        if ($bundleId === '') {
            throw new RuntimeHttpException('PROGRAM_PUBLICATION_TEST_BUNDLE_REQUIRED', 'A aprovacao final precisa apontar o bundle de testes executados.', 422, [
                'programCode' => $version->getProgramCode(),
                'builderProgramVersionId' => $version->getId(),
                'approvalId' => $approval->getId(),
            ]);
        }

        $tests = $this->tests->findByBundle($version->getProgramCode(), (int) $version->getId(), $bundleId);
        if (!$tests) {
            throw new RuntimeHttpException('PROGRAM_PUBLICATION_TESTS_REQUIRED', 'A publicacao exige roteiros de teste executados.', 422, [
                'programCode' => $version->getProgramCode(),
                'builderProgramVersionId' => $version->getId(),
                'testExecutionBundleId' => $bundleId,
            ]);
        }

        foreach ($tests as $test) {
            if ($test->getStatus() !== 'passed') {
                throw new RuntimeHttpException('PROGRAM_PUBLICATION_TESTS_NOT_PASSED', 'A publicacao exige que todos os roteiros do bundle estejam aprovados.', 422, [
                    'programCode' => $version->getProgramCode(),
                    'builderProgramVersionId' => $version->getId(),
                    'testExecutionBundleId' => $bundleId,
                    'testExecutionId' => $test->getId(),
                    'testStatus' => $test->getStatus(),
                ]);
            }
        }

        return $grant;
    }

    public function consumeGrant(ProgramChangeGrant $grant): void
    {
        $grant
            ->setStatus('consumed')
            ->setConsumedAt(new \DateTimeImmutable());
        $this->entityManager->persist($grant);
        $this->releaseLocksForGrant((int) $grant->getId());
    }

    public function assertLockGrantStillActive(?int $grantId, string $scopeType, string $scopeCode): ?ProgramChangeGrant
    {
        if (!$grantId) {
            return null;
        }

        $grant = $this->grants->findOneForUserById($grantId, $this->permissions->getUserId());
        if (!$grant || $grant->getStatus() !== 'active') {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_INACTIVE', 'A autorizacao vinculada ao lock nao esta mais ativa.', 409, [
                'grantId' => $grantId,
                'scopeType' => $scopeType,
                'scopeCode' => $scopeCode,
            ]);
        }

        return $grant;
    }

    public function grantPayload(?ProgramChangeGrant $grant): ?array
    {
        if (!$grant) {
            return null;
        }

        return [
            'id' => $grant->getId(),
            'requestId' => $grant->getRequest()->getId(),
            'requestCode' => $grant->getRequest()->getRequestCode(),
            'programCode' => $grant->getProgramCode(),
            'builderEntityCode' => $grant->getBuilderEntityCode(),
            'grantedToUserId' => $grant->getGrantedToUserId(),
            'allowedActions' => $grant->getAllowedActions(),
            'status' => $grant->getStatus(),
            'validUntilPublish' => $grant->isValidUntilPublish(),
            'consumedAt' => $grant->getConsumedAt()?->format(DATE_ATOM),
            'updatedAt' => $grant->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    public function governanceSummary(BuilderProgramVersion $version): array
    {
        $request = null;
        $grant = null;
        if ($this->isStandardProgram($version->getProgramOrigin(), $version->getOwnerScope())) {
            $request = $this->requests->findLatestForUser($version->getProgramCode(), $version->getBuilderEntityCode(), $this->permissions->getUserId());
            $grant = $this->grants->findActiveForUser($version->getProgramCode(), $version->getBuilderEntityCode(), $this->permissions->getUserId());
        }
        $approval = $version->getId() ? $this->approvals->findActiveApproval($version->getProgramCode(), (int) $version->getId()) : null;

        return [
            'requiresGovernance' => $this->isStandardProgram($version->getProgramOrigin(), $version->getOwnerScope()),
            'request' => $request ? [
                'id' => $request->getId(),
                'requestCode' => $request->getRequestCode(),
                'status' => $request->getStatus(),
                'requestedActions' => $request->getRequestedActions(),
                'approvedBy' => $request->getApprovedBy(),
                'approvedAt' => $request->getApprovedAt()?->format(DATE_ATOM),
            ] : null,
            'grant' => $this->grantPayload($grant),
            'approval' => $approval ? [
                'id' => $approval->getId(),
                'status' => $approval->getStatus(),
                'testExecutionBundleId' => $approval->getTestExecutionBundleId(),
                'approvedBy' => $approval->getApprovedBy(),
                'approvedAt' => $approval->getApprovedAt()?->format(DATE_ATOM),
            ] : null,
            'retentionPolicy' => $this->retentionPolicy->getPolicy(),
        ];
    }

    public function dashboard(string $programCode, ?int $builderProgramVersionId = null): array
    {
        $criteria = ['programCode' => $programCode];
        if ($builderProgramVersionId) {
            $approvalCriteria = [
                'programCode' => $programCode,
                'builderProgramVersionId' => $builderProgramVersionId,
            ];
            $testCriteria = $approvalCriteria;
        } else {
            $approvalCriteria = ['programCode' => $programCode];
            $testCriteria = ['programCode' => $programCode];
        }

        $requests = array_map(fn (ProgramChangeRequest $item): array => [
            'id' => $item->getId(),
            'requestCode' => $item->getRequestCode(),
            'programCode' => $item->getProgramCode(),
            'builderEntityCode' => $item->getBuilderEntityCode(),
            'requestedBy' => $item->getRequestedBy(),
            'requestedActions' => $item->getRequestedActions(),
            'reason' => $item->getReason(),
            'status' => $item->getStatus(),
            'approvedBy' => $item->getApprovedBy(),
            'approvedAt' => $item->getApprovedAt()?->format(DATE_ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
        ], $this->requests->findBy($criteria, ['updatedAt' => 'DESC', 'id' => 'DESC'], 10));

        $grants = array_map(fn (ProgramChangeGrant $item): array => [
            'id' => $item->getId(),
            'requestId' => $item->getRequest()->getId(),
            'requestCode' => $item->getRequest()->getRequestCode(),
            'programCode' => $item->getProgramCode(),
            'builderEntityCode' => $item->getBuilderEntityCode(),
            'grantedToUserId' => $item->getGrantedToUserId(),
            'allowedActions' => $item->getAllowedActions(),
            'status' => $item->getStatus(),
            'consumedAt' => $item->getConsumedAt()?->format(DATE_ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
        ], $this->grants->findBy($criteria, ['updatedAt' => 'DESC', 'id' => 'DESC'], 10));

        $approvals = array_map(fn (ProgramPublicationApproval $item): array => [
            'id' => $item->getId(),
            'programCode' => $item->getProgramCode(),
            'builderProgramVersionId' => $item->getBuilderProgramVersionId(),
            'requestedBy' => $item->getRequestedBy(),
            'approvedBy' => $item->getApprovedBy(),
            'status' => $item->getStatus(),
            'testExecutionBundleId' => $item->getTestExecutionBundleId(),
            'approvedAt' => $item->getApprovedAt()?->format(DATE_ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
        ], $this->approvals->findBy($approvalCriteria, ['updatedAt' => 'DESC', 'id' => 'DESC'], 10));

        $tests = array_map(fn (ProgramTestExecution $item): array => [
            'id' => $item->getId(),
            'programCode' => $item->getProgramCode(),
            'builderProgramVersionId' => $item->getBuilderProgramVersionId(),
            'bundleId' => $item->getBundleId(),
            'testPlanId' => $item->getTestPlanId(),
            'executedBy' => $item->getExecutedBy(),
            'status' => $item->getStatus(),
            'executedAt' => $item->getExecutedAt()->format(DATE_ATOM),
            'notes' => $item->getNotes(),
        ], $this->tests->findBy($testCriteria, ['executedAt' => 'DESC', 'id' => 'DESC'], 10));

        $currentVersion = $builderProgramVersionId
            ? $this->versions->find($builderProgramVersionId)
            : $this->versions->findPublishedByProgramCode($programCode);
        if (!$currentVersion instanceof BuilderProgramVersion) {
            $versions = $this->versions->findByProgramCodeOrdered($programCode);
            $currentVersion = $versions[0] ?? null;
        }

        $activeLocks = array_map(fn (BuilderEditorLock $item): array => [
            'id' => $item->getId(),
            'scopeType' => $item->getScopeType(),
            'scopeCode' => $item->getScopeCode(),
            'userId' => $item->getUserId(),
            'sessionId' => $item->getSessionId(),
            'grantId' => $item->getGrantId(),
            'status' => $item->getStatus(),
            'expiresAt' => $item->getExpiresAt()?->format(DATE_ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
        ], $this->editorLocks->findBy([
            'tenantId' => $this->permissions->getTenantId(),
            'status' => 'active',
        ], ['updatedAt' => 'DESC'], 25));

        return [
            'requests' => $requests,
            'grants' => $grants,
            'approvals' => $approvals,
            'tests' => $tests,
            'currentVersion' => $currentVersion ? [
                'id' => $currentVersion->getId(),
                'version' => $currentVersion->getVersion(),
                'status' => $currentVersion->getStatus(),
                'updatedAt' => $currentVersion->getUpdatedAt()->format(DATE_ATOM),
                'publishedAt' => $currentVersion->getPublishedAt()?->format(DATE_ATOM),
            ] : null,
            'activeLocks' => array_values(array_filter($activeLocks, static fn (array $item): bool => in_array(($item['scopeCode'] ?? ''), [$programCode, (string) ($currentVersion?->getBuilderEntityCode() ?? '')], true))),
            'timeline' => $this->buildTimeline($programCode, $requests, $grants, $tests, $approvals, $currentVersion, $activeLocks),
            'retentionPolicy' => $this->retentionPolicy->getPolicy(),
            'summary' => [
                'pendingRequests' => count(array_filter($requests, static fn (array $item): bool => ($item['status'] ?? '') === 'pending')),
                'activeGrants' => count(array_filter($grants, static fn (array $item): bool => ($item['status'] ?? '') === 'active')),
                'approvedPublications' => count(array_filter($approvals, static fn (array $item): bool => ($item['status'] ?? '') === 'approved')),
                'passedTests' => count(array_filter($tests, static fn (array $item): bool => ($item['status'] ?? '') === 'passed')),
            ],
            'suggestedActions' => $this->buildSuggestedActions($requests, $grants, $tests, $approvals, $currentVersion),
        ];
    }

    public function retentionPolicy(): array
    {
        return $this->retentionPolicy->describePolicy();
    }

    public function updateRetentionPolicy(array $values): array
    {
        return $this->retentionPolicy->updatePolicy($values);
    }

    private function requireActiveGrant(string $programCode, ?string $builderEntityCode): ProgramChangeGrant
    {
        $grant = $this->grants->findActiveForUser($programCode, $builderEntityCode, $this->permissions->getUserId());
        if (!$grant) {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_REQUIRED', 'Programa padrao exige autorizacao ativa para edicao.', 403, [
                'programCode' => $programCode,
                'builderEntityCode' => $builderEntityCode,
                'userId' => $this->permissions->getUserId(),
            ]);
        }

        return $grant;
    }

    private function requireActiveLockWithGrant(string $scopeType, string $scopeCode, ProgramChangeGrant $grant): BuilderEditorLock
    {
        $lock = $this->editorLocks->findActiveByScope($scopeType, $scopeCode, $this->permissions->getTenantId());
        if (!$lock || $lock->getSessionId() !== $this->permissions->getSessionId() || $lock->getGrantId() !== $grant->getId()) {
            throw new RuntimeHttpException('PROGRAM_AUTHORING_LOCK_REQUIRED', 'Programa padrao exige lock ativo vinculado a autorizacao para editar ou publicar.', 409, [
                'scopeType' => $scopeType,
                'scopeCode' => $scopeCode,
                'grantId' => $grant->getId(),
                'sessionId' => $this->permissions->getSessionId(),
            ]);
        }

        return $lock;
    }

    private function isStandardProgram(?string $programOrigin, ?string $ownerScope): bool
    {
        return ($programOrigin ?? 'standard') === 'standard'
            && ($ownerScope ?? 'system') === 'system';
    }

    /**
     * @param list<array<string, mixed>> $requests
     * @param list<array<string, mixed>> $grants
     * @param list<array<string, mixed>> $tests
     * @param list<array<string, mixed>> $approvals
     * @param list<array<string, mixed>> $activeLocks
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(
        string $programCode,
        array $requests,
        array $grants,
        array $tests,
        array $approvals,
        ?BuilderProgramVersion $currentVersion,
        array $activeLocks,
    ): array {
        $events = [];
        foreach ($requests as $item) {
            $events[] = [
                'type' => 'request',
                'label' => 'Solicitacao',
                'status' => (string) ($item['status'] ?? 'pending'),
                'description' => (string) ($item['requestCode'] ?? $programCode),
                'timestamp' => (string) ($item['updatedAt'] ?? ''),
            ];
        }
        foreach ($grants as $item) {
            $events[] = [
                'type' => 'grant',
                'label' => 'Grant',
                'status' => (string) ($item['status'] ?? 'active'),
                'description' => 'Grant #' . (string) ($item['id'] ?? '-') . ' para ' . (string) ($item['grantedToUserId'] ?? '-'),
                'timestamp' => (string) ($item['updatedAt'] ?? ''),
            ];
        }
        foreach ($activeLocks as $item) {
            $events[] = [
                'type' => 'lock',
                'label' => 'Lock',
                'status' => (string) ($item['status'] ?? 'active'),
                'description' => (string) ($item['scopeType'] ?? 'scope') . ': ' . (string) ($item['scopeCode'] ?? '-'),
                'timestamp' => (string) ($item['updatedAt'] ?? ''),
            ];
        }
        foreach ($tests as $item) {
            $events[] = [
                'type' => 'test',
                'label' => 'Teste',
                'status' => (string) ($item['status'] ?? 'passed'),
                'description' => (string) ($item['bundleId'] ?? '-') . ' / ' . (string) ($item['testPlanId'] ?? '-'),
                'timestamp' => (string) ($item['executedAt'] ?? ''),
            ];
        }
        foreach ($approvals as $item) {
            $events[] = [
                'type' => 'approval',
                'label' => 'Aprovacao',
                'status' => (string) ($item['status'] ?? 'approved'),
                'description' => 'Aprovacao #' . (string) ($item['id'] ?? '-') . ' / bundle ' . (string) ($item['testExecutionBundleId'] ?? '-'),
                'timestamp' => (string) ($item['approvedAt'] ?? $item['updatedAt'] ?? ''),
            ];
        }
        if ($currentVersion instanceof BuilderProgramVersion) {
            $events[] = [
                'type' => 'publish',
                'label' => 'Versao',
                'status' => (string) $currentVersion->getStatus(),
                'description' => 'Versao ' . (string) $currentVersion->getVersion(),
                'timestamp' => (string) ($currentVersion->getPublishedAt()?->format(DATE_ATOM) ?: $currentVersion->getUpdatedAt()->format(DATE_ATOM)),
            ];
        }

        usort($events, static function (array $left, array $right): int {
            return strcmp((string) ($right['timestamp'] ?? ''), (string) ($left['timestamp'] ?? ''));
        });

        return array_slice($events, 0, 20);
    }

    /**
     * @param list<array<string, mixed>> $requests
     * @param list<array<string, mixed>> $grants
     * @param list<array<string, mixed>> $tests
     * @param list<array<string, mixed>> $approvals
     * @return list<array<string, string>>
     */
    private function buildSuggestedActions(
        array $requests,
        array $grants,
        array $tests,
        array $approvals,
        ?BuilderProgramVersion $currentVersion,
    ): array {
        $actions = [];
        $pendingRequest = array_values(array_filter($requests, static fn (array $item): bool => ($item['status'] ?? '') === 'pending'))[0] ?? null;
        $activeGrant = array_values(array_filter($grants, static fn (array $item): bool => ($item['status'] ?? '') === 'active'))[0] ?? null;
        $frozenGrant = array_values(array_filter($grants, static fn (array $item): bool => ($item['status'] ?? '') === 'frozen'))[0] ?? null;
        $approved = array_values(array_filter($approvals, static fn (array $item): bool => ($item['status'] ?? '') === 'approved'))[0] ?? null;
        $passedTest = array_values(array_filter($tests, static fn (array $item): bool => ($item['status'] ?? '') === 'passed'))[0] ?? null;

        if ($pendingRequest) {
            $actions[] = ['severity' => 'warning', 'text' => 'Existe solicitacao pendente. O proximo passo esperado e liberar ou rejeitar o grant.'];
        }
        if ($frozenGrant) {
            $actions[] = ['severity' => 'warning', 'text' => 'Existe grant congelado. Decida se ele deve voltar a `active` ou ser revogado.'];
        }
        if ($activeGrant && !$passedTest) {
            $actions[] = ['severity' => 'info', 'text' => 'Grant ativo sem bundle aprovado. Registrar testes e evidencias antes da aprovacao final.'];
        }
        if ($passedTest && !$approved) {
            $actions[] = ['severity' => 'info', 'text' => 'Bundle aprovado sem aprovacao final. Registrar a aprovacao para liberar o publish.'];
        }
        if ($approved && $currentVersion instanceof BuilderProgramVersion && $currentVersion->getStatus() !== 'published') {
            $actions[] = ['severity' => 'success', 'text' => 'Gate completo para publish. A versao ja pode seguir para publicacao governada.'];
        }
        if (!$actions) {
            $actions[] = ['severity' => 'info', 'text' => 'Sem pendencias imediatas. Use o historico e a linha do tempo para auditoria do fluxo.'];
        }

        return $actions;
    }

    private function findStandardProgramVersionByEntity(string $builderEntityCode): ?BuilderProgramVersion
    {
        foreach ($this->versions->findAll() as $version) {
            if ($version->getBuilderEntityCode() !== $builderEntityCode) {
                continue;
            }
            if ($version->getStatus() !== 'published') {
                continue;
            }
            if (!$this->isStandardProgram($version->getProgramOrigin(), $version->getOwnerScope())) {
                continue;
            }

            return $version;
        }

        return null;
    }

    private function generateRequestCode(string $programCode): string
    {
        return strtoupper($programCode) . '-' . (new \DateTimeImmutable())->format('YmdHis');
    }

    private function releaseLocksForGrant(int $grantId): void
    {
        foreach ($this->editorLocks->findBy(['grantId' => $grantId, 'status' => 'active']) as $lock) {
            $lock->release();
            $this->entityManager->persist($lock);
        }
    }
}
