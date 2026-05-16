<?php

namespace App\Runtime;

use App\Entity\BuilderEntity;
use App\Entity\BuilderEntitySituation;
use App\Entity\BuilderEntitySituationTransition;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderApiSource;
use App\Entity\BuilderModule;
use App\Entity\BuilderProgramOverlay;
use App\Entity\BuilderProgramOverlayVersion;
use App\Entity\BuilderProgramVersion;
use App\Entity\ImportExportMapping;
use App\Entity\ImportExportMappingVersion;
use App\Entity\ImportExportSchedule;
use App\Entity\Program;
use App\Entity\ProgramChangeGrant;
use App\Entity\ProgramChangeRequest;
use App\Entity\ProgramPublicationApproval;
use App\Entity\ProgramTestExecution;
use App\Entity\RuntimeEndpoint;
use App\Entity\RuntimeLockPolicy;
use App\Entity\ScreenDefinition;
use App\Entity\SystemRecordIntegrity;
use App\Entity\SystemOption;
use App\Entity\SystemOptionList;
use App\Entity\SystemParameter;
use App\Entity\SystemParameterValue;
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
use App\Repository\ProgramRepository;
use App\Repository\ProgramChangeGrantRepository;
use App\Repository\ProgramChangeRequestRepository;
use App\Repository\ProgramPublicationApprovalRepository;
use App\Repository\ProgramTestExecutionRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\RuntimeLockPolicyRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Repository\SystemRecordIntegrityRepository;
use App\Repository\SystemOptionListRepository;
use App\Repository\SystemOptionRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use Doctrine\ORM\EntityManagerInterface;

class StructuralIntegrityService
{
    private const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemRecordIntegrityRepository $integrities,
        private readonly ProgramRepository $programs,
        private readonly BuilderApiSourceRepository $apiSources,
        private readonly BuilderModuleRepository $modules,
        private readonly BuilderProgramVersionRepository $versions,
        private readonly BuilderEntityRepository $entities,
        private readonly BuilderEntitySituationRepository $situations,
        private readonly BuilderEntitySituationTransitionRepository $situationTransitions,
        private readonly BuilderEntityVersionRepository $entityVersions,
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEndpointRepository $endpoints,
        private readonly RuntimeLockPolicyRepository $lockPolicies,
        private readonly BuilderProgramOverlayRepository $overlays,
        private readonly BuilderProgramOverlayVersionRepository $overlayVersions,
        private readonly ProgramChangeRequestRepository $changeRequests,
        private readonly ProgramChangeGrantRepository $changeGrants,
        private readonly ProgramPublicationApprovalRepository $publicationApprovals,
        private readonly ProgramTestExecutionRepository $testExecutions,
        private readonly ImportExportMappingRepository $importExportMappings,
        private readonly ImportExportMappingVersionRepository $importExportMappingVersions,
        private readonly ImportExportScheduleRepository $importExportSchedules,
        private readonly SystemOptionListRepository $systemOptionLists,
        private readonly SystemOptionRepository $systemOptions,
        private readonly SystemParameterRepository $systemParameters,
        private readonly SystemParameterValueRepository $systemParameterValues,
        private readonly RuntimeNotificationService $notifications,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function assertProgram(Program $program): void
    {
        $this->assertEntityIntegrity('builder_program', $program->getId(), $this->programPayload($program));
    }

    public function assertProgramVersion(BuilderProgramVersion $version): void
    {
        $this->assertEntityIntegrity('builder_program_version', $version->getId(), $this->programVersionPayload($version));
    }

    public function assertApiSource(BuilderApiSource $source): void
    {
        $this->assertEntityIntegrity('builder_api_source', $source->getId(), $this->apiSourcePayload($source));
    }

    public function assertBuilderModule(BuilderModule $module): void
    {
        $this->assertEntityIntegrity('builder_module', $module->getId(), $this->builderModulePayload($module));
    }

    public function assertBuilderEntity(BuilderEntity $entity): void
    {
        $this->assertEntityIntegrity('builder_entity', $entity->getId(), $this->builderEntityPayload($entity));
    }

    public function assertBuilderEntitySituation(BuilderEntitySituation $situation): void
    {
        $this->assertEntityIntegrity('builder_entity_situation', $situation->getId(), $this->builderEntitySituationPayload($situation));
    }

    public function assertBuilderEntitySituationTransition(BuilderEntitySituationTransition $transition): void
    {
        $this->assertEntityIntegrity('builder_entity_situation_transition', $transition->getId(), $this->builderEntitySituationTransitionPayload($transition));
    }

    public function assertBuilderEntityVersion(BuilderEntityVersion $version): void
    {
        $this->assertEntityIntegrity('builder_entity_version', $version->getId(), $this->builderEntityVersionPayload($version));
    }

    public function assertScreen(ScreenDefinition $screen): void
    {
        $this->assertEntityIntegrity('screen_definition', $screen->getId(), $this->screenPayload($screen));
    }

    public function assertEndpoint(RuntimeEndpoint $endpoint): void
    {
        $this->assertEntityIntegrity('runtime_endpoint', $endpoint->getId(), $this->endpointPayload($endpoint));
    }

    public function assertRuntimeLockPolicy(RuntimeLockPolicy $policy): void
    {
        $this->assertEntityIntegrity('runtime_lock_policy', $policy->getId(), $this->runtimeLockPolicyPayload($policy));
    }

    public function assertOverlay(BuilderProgramOverlay $overlay): void
    {
        $this->assertEntityIntegrity('builder_program_overlay', $overlay->getId(), $this->overlayPayload($overlay));
    }

    public function assertOverlayVersion(BuilderProgramOverlayVersion $version): void
    {
        $this->assertEntityIntegrity('builder_program_overlay_version', $version->getId(), $this->overlayVersionPayload($version));
    }

    public function assertProgramChangeRequest(ProgramChangeRequest $request): void
    {
        $this->assertEntityIntegrity('program_change_request', $request->getId(), $this->programChangeRequestPayload($request));
    }

    public function assertProgramChangeGrant(ProgramChangeGrant $grant): void
    {
        $this->assertEntityIntegrity('program_change_grant', $grant->getId(), $this->programChangeGrantPayload($grant));
    }

    public function assertProgramPublicationApproval(ProgramPublicationApproval $approval): void
    {
        $this->assertEntityIntegrity('program_publication_approval', $approval->getId(), $this->programPublicationApprovalPayload($approval));
    }

    public function assertProgramTestExecution(ProgramTestExecution $testExecution): void
    {
        $this->assertEntityIntegrity('program_test_execution', $testExecution->getId(), $this->programTestExecutionPayload($testExecution));
    }

    public function assertImportExportMapping(ImportExportMapping $mapping): void
    {
        $this->assertEntityIntegrity('import_export_mapping', $mapping->getId(), $this->importExportMappingPayload($mapping));
    }

    public function assertImportExportMappingVersion(ImportExportMappingVersion $version): void
    {
        $this->assertEntityIntegrity('import_export_mapping_version', $version->getId(), $this->importExportMappingVersionPayload($version));
    }

    public function assertImportExportSchedule(ImportExportSchedule $schedule): void
    {
        $this->assertEntityIntegrity('import_export_schedule', $schedule->getId(), $this->importExportSchedulePayload($schedule));
    }

    public function assertSystemParameter(SystemParameter $parameter): void
    {
        $this->assertEntityIntegrity('system_parameter', $parameter->getId(), $this->systemParameterPayload($parameter));
    }

    public function assertSystemParameterValue(SystemParameterValue $parameterValue): void
    {
        $this->assertEntityIntegrity('system_parameter_value', $parameterValue->getId(), $this->systemParameterValuePayload($parameterValue));
    }

    public function assertSystemOptionList(SystemOptionList $optionList): void
    {
        $this->assertEntityIntegrity('system_option_list', $optionList->getId(), $this->systemOptionListPayload($optionList));
    }

    public function assertSystemOption(SystemOption $option): void
    {
        $this->assertEntityIntegrity('system_option', $option->getId(), $this->systemOptionPayload($option));
    }

    public function signProgram(Program $program, ?array $metadata = null): void
    {
        $this->signEntity('builder_program', $program->getId(), $this->programPayload($program), $metadata);
    }

    public function signProgramVersion(BuilderProgramVersion $version, ?array $metadata = null): void
    {
        $this->signEntity('builder_program_version', $version->getId(), $this->programVersionPayload($version), $metadata);
    }

    public function signApiSource(BuilderApiSource $source, ?array $metadata = null): void
    {
        $this->signEntity('builder_api_source', $source->getId(), $this->apiSourcePayload($source), $metadata);
    }

    public function signBuilderModule(BuilderModule $module, ?array $metadata = null): void
    {
        $this->signEntity('builder_module', $module->getId(), $this->builderModulePayload($module), $metadata);
    }

    public function signBuilderEntity(BuilderEntity $entity, ?array $metadata = null): void
    {
        $this->signEntity('builder_entity', $entity->getId(), $this->builderEntityPayload($entity), $metadata);
    }

    public function signBuilderEntitySituation(BuilderEntitySituation $situation, ?array $metadata = null): void
    {
        $this->signEntity('builder_entity_situation', $situation->getId(), $this->builderEntitySituationPayload($situation), $metadata);
    }

    public function signBuilderEntitySituationTransition(BuilderEntitySituationTransition $transition, ?array $metadata = null): void
    {
        $this->signEntity('builder_entity_situation_transition', $transition->getId(), $this->builderEntitySituationTransitionPayload($transition), $metadata);
    }

    public function signBuilderEntityVersion(BuilderEntityVersion $version, ?array $metadata = null): void
    {
        $this->signEntity('builder_entity_version', $version->getId(), $this->builderEntityVersionPayload($version), $metadata);
    }

    public function signScreen(ScreenDefinition $screen, ?array $metadata = null): void
    {
        $this->signEntity('screen_definition', $screen->getId(), $this->screenPayload($screen), $metadata);
    }

    public function signEndpoint(RuntimeEndpoint $endpoint, ?array $metadata = null): void
    {
        $this->signEntity('runtime_endpoint', $endpoint->getId(), $this->endpointPayload($endpoint), $metadata);
    }

    public function signRuntimeLockPolicy(RuntimeLockPolicy $policy, ?array $metadata = null): void
    {
        $this->signEntity('runtime_lock_policy', $policy->getId(), $this->runtimeLockPolicyPayload($policy), $metadata);
    }

    public function signOverlay(BuilderProgramOverlay $overlay, ?array $metadata = null): void
    {
        $this->signEntity('builder_program_overlay', $overlay->getId(), $this->overlayPayload($overlay), $metadata);
    }

    public function signOverlayVersion(BuilderProgramOverlayVersion $version, ?array $metadata = null): void
    {
        $this->signEntity('builder_program_overlay_version', $version->getId(), $this->overlayVersionPayload($version), $metadata);
    }

    public function signProgramChangeRequest(ProgramChangeRequest $request, ?array $metadata = null): void
    {
        $this->signEntity('program_change_request', $request->getId(), $this->programChangeRequestPayload($request), $metadata);
    }

    public function signProgramChangeGrant(ProgramChangeGrant $grant, ?array $metadata = null): void
    {
        $this->signEntity('program_change_grant', $grant->getId(), $this->programChangeGrantPayload($grant), $metadata);
    }

    public function signProgramPublicationApproval(ProgramPublicationApproval $approval, ?array $metadata = null): void
    {
        $this->signEntity('program_publication_approval', $approval->getId(), $this->programPublicationApprovalPayload($approval), $metadata);
    }

    public function signProgramTestExecution(ProgramTestExecution $testExecution, ?array $metadata = null): void
    {
        $this->signEntity('program_test_execution', $testExecution->getId(), $this->programTestExecutionPayload($testExecution), $metadata);
    }

    public function signImportExportMapping(ImportExportMapping $mapping, ?array $metadata = null): void
    {
        $this->signEntity('import_export_mapping', $mapping->getId(), $this->importExportMappingPayload($mapping), $metadata);
    }

    public function signImportExportMappingVersion(ImportExportMappingVersion $version, ?array $metadata = null): void
    {
        $this->signEntity('import_export_mapping_version', $version->getId(), $this->importExportMappingVersionPayload($version), $metadata);
    }

    public function signImportExportSchedule(ImportExportSchedule $schedule, ?array $metadata = null): void
    {
        $this->signEntity('import_export_schedule', $schedule->getId(), $this->importExportSchedulePayload($schedule), $metadata);
    }

    public function signSystemParameter(SystemParameter $parameter, ?array $metadata = null): void
    {
        $this->signEntity('system_parameter', $parameter->getId(), $this->systemParameterPayload($parameter), $metadata);
    }

    public function signSystemParameterValue(SystemParameterValue $parameterValue, ?array $metadata = null): void
    {
        $this->signEntity('system_parameter_value', $parameterValue->getId(), $this->systemParameterValuePayload($parameterValue), $metadata);
    }

    public function signSystemOptionList(SystemOptionList $optionList, ?array $metadata = null): void
    {
        $this->signEntity('system_option_list', $optionList->getId(), $this->systemOptionListPayload($optionList), $metadata);
    }

    public function signSystemOption(SystemOption $option, ?array $metadata = null): void
    {
        $this->signEntity('system_option', $option->getId(), $this->systemOptionPayload($option), $metadata);
    }

    public function signTarget(string $tableName, int $recordId, ?array $metadata = null): void
    {
        if (!$this->supportsTableName($tableName)) {
            return;
        }
        $this->signEntity($tableName, $recordId, $this->payloadForTarget($tableName, $recordId), $metadata);
    }

    public function deleteTarget(string $tableName, int $recordId): void
    {
        if (!$this->supportsTableName($tableName)) {
            return;
        }
        $this->integrities->removeByTarget($tableName, $recordId);
    }

    public function supportsTableName(string $tableName): bool
    {
        return in_array($tableName, [
            'builder_program',
            'builder_program_version',
            'builder_api_source',
            'builder_module',
            'builder_entity',
            'builder_entity_situation',
            'builder_entity_situation_transition',
            'builder_entity_version',
            'screen_definition',
            'runtime_endpoint',
            'runtime_lock_policy',
            'builder_program_overlay',
            'builder_program_overlay_version',
            'program_change_request',
            'program_change_grant',
            'program_publication_approval',
            'program_test_execution',
            'import_export_mapping',
            'import_export_mapping_version',
            'import_export_schedule',
            'system_option_list',
            'system_option',
            'system_parameter',
            'system_parameter_value',
        ], true);
    }

    public function flushPendingChanges(): void
    {
        $this->entityManager->flush();
    }

    public function backfillAll(): void
    {
        foreach ($this->programs->findAll() as $program) {
            if ($program->getId()) {
                $this->signProgram($program, ['source' => 'backfill']);
            }
        }
        foreach ($this->versions->findAll() as $version) {
            if ($version->getId()) {
                $this->signProgramVersion($version, ['source' => 'backfill']);
            }
        }
        foreach ($this->apiSources->findAll() as $source) {
            if ($source->getId()) {
                $this->signApiSource($source, ['source' => 'backfill']);
            }
        }
        foreach ($this->modules->findAll() as $module) {
            if ($module->getId()) {
                $this->signBuilderModule($module, ['source' => 'backfill']);
            }
        }
        foreach ($this->entities->findAll() as $entity) {
            if ($entity->getId()) {
                $this->signBuilderEntity($entity, ['source' => 'backfill']);
            }
        }
        foreach ($this->situations->findAll() as $situation) {
            if ($situation->getId()) {
                $this->signBuilderEntitySituation($situation, ['source' => 'backfill']);
            }
        }
        foreach ($this->situationTransitions->findAll() as $transition) {
            if ($transition->getId()) {
                $this->signBuilderEntitySituationTransition($transition, ['source' => 'backfill']);
            }
        }
        foreach ($this->entityVersions->findAll() as $version) {
            if ($version->getId()) {
                $this->signBuilderEntityVersion($version, ['source' => 'backfill']);
            }
        }
        foreach ($this->screens->findAll() as $screen) {
            if ($screen->getId()) {
                $this->signScreen($screen, ['source' => 'backfill']);
            }
        }
        foreach ($this->endpoints->findAll() as $endpoint) {
            if ($endpoint->getId()) {
                $this->signEndpoint($endpoint, ['source' => 'backfill']);
            }
        }
        foreach ($this->lockPolicies->findAll() as $policy) {
            if ($policy->getId()) {
                $this->signRuntimeLockPolicy($policy, ['source' => 'backfill']);
            }
        }
        foreach ($this->overlays->findAll() as $overlay) {
            if ($overlay->getId()) {
                $this->signOverlay($overlay, ['source' => 'backfill']);
            }
        }
        foreach ($this->overlayVersions->findAll() as $version) {
            if ($version->getId()) {
                $this->signOverlayVersion($version, ['source' => 'backfill']);
            }
        }
        foreach ($this->changeRequests->findAll() as $request) {
            if ($request->getId()) {
                $this->signProgramChangeRequest($request, ['source' => 'backfill']);
            }
        }
        foreach ($this->changeGrants->findAll() as $grant) {
            if ($grant->getId()) {
                $this->signProgramChangeGrant($grant, ['source' => 'backfill']);
            }
        }
        foreach ($this->publicationApprovals->findAll() as $approval) {
            if ($approval->getId()) {
                $this->signProgramPublicationApproval($approval, ['source' => 'backfill']);
            }
        }
        foreach ($this->testExecutions->findAll() as $testExecution) {
            if ($testExecution->getId()) {
                $this->signProgramTestExecution($testExecution, ['source' => 'backfill']);
            }
        }
        foreach ($this->importExportMappings->findAll() as $mapping) {
            if ($mapping->getId()) {
                $this->signImportExportMapping($mapping, ['source' => 'backfill']);
            }
        }
        foreach ($this->importExportMappingVersions->findAll() as $version) {
            if ($version->getId()) {
                $this->signImportExportMappingVersion($version, ['source' => 'backfill']);
            }
        }
        foreach ($this->importExportSchedules->findAll() as $schedule) {
            if ($schedule->getId()) {
                $this->signImportExportSchedule($schedule, ['source' => 'backfill']);
            }
        }
        foreach ($this->systemOptionLists->findAll() as $optionList) {
            if ($optionList->getId()) {
                $this->signSystemOptionList($optionList, ['source' => 'backfill']);
            }
        }
        foreach ($this->systemOptions->findAll() as $option) {
            if ($option->getId()) {
                $this->signSystemOption($option, ['source' => 'backfill']);
            }
        }
        foreach ($this->systemParameters->findAll() as $parameter) {
            if ($parameter->getId()) {
                $this->signSystemParameter($parameter, ['source' => 'backfill']);
            }
        }
        foreach ($this->systemParameterValues->findAll() as $parameterValue) {
            if ($parameterValue->getId()) {
                $this->signSystemParameterValue($parameterValue, ['source' => 'backfill']);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function verifyAll(): array
    {
        $results = [];
        foreach ($this->programs->findAll() as $program) {
            if ($program->getId()) {
                $results[] = $this->verifyTarget('builder_program', (int) $program->getId());
            }
        }
        foreach ($this->versions->findAll() as $version) {
            if ($version->getId()) {
                $results[] = $this->verifyTarget('builder_program_version', (int) $version->getId());
            }
        }
        foreach ($this->apiSources->findAll() as $source) {
            if ($source->getId()) {
                $results[] = $this->verifyTarget('builder_api_source', (int) $source->getId());
            }
        }
        foreach ($this->modules->findAll() as $module) {
            if ($module->getId()) {
                $results[] = $this->verifyTarget('builder_module', (int) $module->getId());
            }
        }
        foreach ($this->entities->findAll() as $entity) {
            if ($entity->getId()) {
                $results[] = $this->verifyTarget('builder_entity', (int) $entity->getId());
            }
        }
        foreach ($this->situations->findAll() as $situation) {
            if ($situation->getId()) {
                $results[] = $this->verifyTarget('builder_entity_situation', (int) $situation->getId());
            }
        }
        foreach ($this->situationTransitions->findAll() as $transition) {
            if ($transition->getId()) {
                $results[] = $this->verifyTarget('builder_entity_situation_transition', (int) $transition->getId());
            }
        }
        foreach ($this->entityVersions->findAll() as $version) {
            if ($version->getId()) {
                $results[] = $this->verifyTarget('builder_entity_version', (int) $version->getId());
            }
        }
        foreach ($this->screens->findAll() as $screen) {
            if ($screen->getId()) {
                $results[] = $this->verifyTarget('screen_definition', (int) $screen->getId());
            }
        }
        foreach ($this->endpoints->findAll() as $endpoint) {
            if ($endpoint->getId()) {
                $results[] = $this->verifyTarget('runtime_endpoint', (int) $endpoint->getId());
            }
        }
        foreach ($this->lockPolicies->findAll() as $policy) {
            if ($policy->getId()) {
                $results[] = $this->verifyTarget('runtime_lock_policy', (int) $policy->getId());
            }
        }
        foreach ($this->overlays->findAll() as $overlay) {
            if ($overlay->getId()) {
                $results[] = $this->verifyTarget('builder_program_overlay', (int) $overlay->getId());
            }
        }
        foreach ($this->overlayVersions->findAll() as $version) {
            if ($version->getId()) {
                $results[] = $this->verifyTarget('builder_program_overlay_version', (int) $version->getId());
            }
        }
        foreach ($this->changeRequests->findAll() as $request) {
            if ($request->getId()) {
                $results[] = $this->verifyTarget('program_change_request', (int) $request->getId());
            }
        }
        foreach ($this->changeGrants->findAll() as $grant) {
            if ($grant->getId()) {
                $results[] = $this->verifyTarget('program_change_grant', (int) $grant->getId());
            }
        }
        foreach ($this->publicationApprovals->findAll() as $approval) {
            if ($approval->getId()) {
                $results[] = $this->verifyTarget('program_publication_approval', (int) $approval->getId());
            }
        }
        foreach ($this->testExecutions->findAll() as $testExecution) {
            if ($testExecution->getId()) {
                $results[] = $this->verifyTarget('program_test_execution', (int) $testExecution->getId());
            }
        }
        foreach ($this->importExportMappings->findAll() as $mapping) {
            if ($mapping->getId()) {
                $results[] = $this->verifyTarget('import_export_mapping', (int) $mapping->getId());
            }
        }
        foreach ($this->importExportMappingVersions->findAll() as $version) {
            if ($version->getId()) {
                $results[] = $this->verifyTarget('import_export_mapping_version', (int) $version->getId());
            }
        }
        foreach ($this->importExportSchedules->findAll() as $schedule) {
            if ($schedule->getId()) {
                $results[] = $this->verifyTarget('import_export_schedule', (int) $schedule->getId());
            }
        }
        foreach ($this->systemOptionLists->findAll() as $optionList) {
            if ($optionList->getId()) {
                $results[] = $this->verifyTarget('system_option_list', (int) $optionList->getId());
            }
        }
        foreach ($this->systemOptions->findAll() as $option) {
            if ($option->getId()) {
                $results[] = $this->verifyTarget('system_option', (int) $option->getId());
            }
        }
        foreach ($this->systemParameters->findAll() as $parameter) {
            if ($parameter->getId()) {
                $results[] = $this->verifyTarget('system_parameter', (int) $parameter->getId());
            }
        }
        foreach ($this->systemParameterValues->findAll() as $parameterValue) {
            if ($parameterValue->getId()) {
                $results[] = $this->verifyTarget('system_parameter_value', (int) $parameterValue->getId());
            }
        }

        return $results;
    }

    public function verifyTarget(string $tableName, int $recordId): array
    {
        $integrity = $this->integrities->findOneByTarget($tableName, $recordId);
        if (!$integrity) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_MISSING', 'Assinatura estrutural ausente para o registro.', 404, [
                'tableName' => $tableName,
                'recordId' => $recordId,
            ]);
        }

        $payload = $this->payloadForTarget($tableName, $recordId);
        try {
            $this->assertEntityIntegrity($tableName, $recordId, $payload);
            $integrity
                ->setLastCheckStatus('valid')
                ->setLastCheckedAt(new \DateTimeImmutable())
                ->setLastErrorMessage(null);
            $this->entityManager->persist($integrity);

            return [
                'tableName' => $tableName,
                'recordId' => $recordId,
                'status' => 'valid',
            ];
        } catch (RuntimeHttpException $error) {
            $previousStatus = $integrity->getLastCheckStatus();
            $integrity
                ->setLastCheckStatus('invalid')
                ->setLastCheckedAt(new \DateTimeImmutable())
                ->setLastErrorMessage($error->getMessage());
            $this->entityManager->persist($integrity);
            if ($previousStatus !== 'invalid') {
                $this->notifications->createAdministrativeNotification(
                    'Integridade estrutural invalida',
                    sprintf('Foi detectada divergencia estrutural em %s#%d.', $tableName, $recordId),
                    [
                        'code' => 'integridade.invalid.' . strtolower($tableName) . '.' . $recordId,
                        'category' => 'integridade',
                        'severity' => 'error',
                        'actionRequired' => true,
                        'linkScreenId' => 'admin.integridade',
                        'metadata' => [
                            'actionLabel' => 'Abrir integridade',
                            'tableName' => $tableName,
                            'recordId' => $recordId,
                            'errorCode' => $error->getErrorCode(),
                            'message' => $error->getMessage(),
                            'actionQuery' => [
                                'filter__table_name' => $tableName,
                                'filter__record_id' => (string) $recordId,
                            ],
                        ],
                    ]
                );
            }

            return [
                'tableName' => $tableName,
                'recordId' => $recordId,
                'status' => 'invalid',
                'errorCode' => $error->getErrorCode(),
                'message' => $error->getMessage(),
            ];
        }
    }

    public function resignTarget(string $tableName, int $recordId, ?array $metadata = null): void
    {
        $reason = trim((string) (($metadata ?? [])['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_REASON_REQUIRED', 'A reassinatura estrutural exige informar um motivo.', 422, [
                'tableName' => $tableName,
                'recordId' => $recordId,
            ]);
        }

        $currentIntegrity = $this->integrities->findOneByTarget($tableName, $recordId);
        $payload = $this->payloadForTarget($tableName, $recordId);
        $timestamp = new \DateTimeImmutable();
        $entry = [
            'action' => 'resign',
            'tableName' => $tableName,
            'recordId' => $recordId,
            'reason' => $reason,
            'performedBy' => $this->permissions->getUserId(),
            'performedAt' => $timestamp->format(DATE_ATOM),
            'statusBefore' => $currentIntegrity?->getLastCheckStatus(),
            'statusAfter' => 'valid',
            'previousSignedAt' => $currentIntegrity?->getSignedAt()?->format(DATE_ATOM),
            'previousSignedBy' => $currentIntegrity?->getSignedBy(),
            'previousPayloadHash' => $currentIntegrity?->getPayloadHash(),
        ];
        $auditTrail = is_array($currentIntegrity?->getMetadata()['auditTrail'] ?? null) ? $currentIntegrity->getMetadata()['auditTrail'] : [];
        $auditTrail[] = $entry;
        $auditTrail = array_slice($auditTrail, -20);

        $this->signEntity($tableName, $recordId, $payload, array_merge([
            'source' => 'resign',
            'reason' => $reason,
            'resignedBy' => $this->permissions->getUserId(),
            'resignedAt' => $timestamp->format(DATE_ATOM),
            'previousLastCheckStatus' => $currentIntegrity?->getLastCheckStatus(),
            'previousSignedAt' => $currentIntegrity?->getSignedAt()?->format(DATE_ATOM),
            'previousSignedBy' => $currentIntegrity?->getSignedBy(),
            'previousPayloadHash' => $currentIntegrity?->getPayloadHash(),
            'lastResignedBy' => $this->permissions->getUserId(),
            'lastResignedAt' => $timestamp->format(DATE_ATOM),
            'lastResignedReason' => $reason,
            'auditTrail' => $auditTrail,
        ], $metadata ?? []));
    }

    private function assertEntityIntegrity(string $tableName, ?int $recordId, array $payload): void
    {
        if (!$recordId) {
            return;
        }

        $integrity = $this->integrities->findOneByTarget($tableName, $recordId);
        if (!$integrity) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_MISSING', 'Assinatura estrutural ausente para o registro.', 409, [
                'tableName' => $tableName,
                'recordId' => $recordId,
            ]);
        }

        [$payloadHash, $signature] = $this->buildSignature($payload);
        if ($integrity->getPayloadHash() !== $payloadHash || $integrity->getSignature() !== $signature) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_VIOLATION', 'Registro estrutural alterado fora do fluxo oficial.', 409, [
                'tableName' => $tableName,
                'recordId' => $recordId,
                'signedAt' => $integrity->getSignedAt()->format(DATE_ATOM),
            ]);
        }
    }

    private function signEntity(string $tableName, ?int $recordId, array $payload, ?array $metadata = null): void
    {
        if (!$recordId) {
            return;
        }

        [$payloadHash, $signature] = $this->buildSignature($payload);
        $integrity = $this->integrities->findOneByTarget($tableName, $recordId) ?? new SystemRecordIntegrity();
        $integrity
            ->setTableName($tableName)
            ->setRecordId($recordId)
            ->setIntegritySchemaVersion(self::SCHEMA_VERSION)
            ->setPayloadHash($payloadHash)
            ->setSignature($signature)
            ->setSignedBy($this->signedBy())
            ->setSignedAt(new \DateTimeImmutable())
            ->setLastCheckStatus('valid')
            ->setLastCheckedAt(new \DateTimeImmutable())
            ->setLastErrorMessage(null)
            ->setMetadata(array_merge($integrity->getMetadata(), $metadata ?? []));
        $this->entityManager->persist($integrity);
    }

    private function payloadForTarget(string $tableName, int $recordId): array
    {
        return match ($tableName) {
            'builder_program' => $this->programPayload($this->requireEntity($this->programs->find($recordId), $tableName, $recordId)),
            'builder_program_version' => $this->programVersionPayload($this->requireEntity($this->versions->find($recordId), $tableName, $recordId)),
            'builder_api_source' => $this->apiSourcePayload($this->requireEntity($this->apiSources->find($recordId), $tableName, $recordId)),
            'builder_module' => $this->builderModulePayload($this->requireEntity($this->modules->find($recordId), $tableName, $recordId)),
            'builder_entity' => $this->builderEntityPayload($this->requireEntity($this->entities->find($recordId), $tableName, $recordId)),
            'builder_entity_situation' => $this->builderEntitySituationPayload($this->requireEntity($this->situations->find($recordId), $tableName, $recordId)),
            'builder_entity_situation_transition' => $this->builderEntitySituationTransitionPayload($this->requireEntity($this->situationTransitions->find($recordId), $tableName, $recordId)),
            'builder_entity_version' => $this->builderEntityVersionPayload($this->requireEntity($this->entityVersions->find($recordId), $tableName, $recordId)),
            'screen_definition' => $this->screenPayload($this->requireEntity($this->screens->find($recordId), $tableName, $recordId)),
            'runtime_endpoint' => $this->endpointPayload($this->requireEntity($this->endpoints->find($recordId), $tableName, $recordId)),
            'runtime_lock_policy' => $this->runtimeLockPolicyPayload($this->requireEntity($this->lockPolicies->find($recordId), $tableName, $recordId)),
            'builder_program_overlay' => $this->overlayPayload($this->requireEntity($this->overlays->find($recordId), $tableName, $recordId)),
            'builder_program_overlay_version' => $this->overlayVersionPayload($this->requireEntity($this->overlayVersions->find($recordId), $tableName, $recordId)),
            'program_change_request' => $this->programChangeRequestPayload($this->requireEntity($this->changeRequests->find($recordId), $tableName, $recordId)),
            'program_change_grant' => $this->programChangeGrantPayload($this->requireEntity($this->changeGrants->find($recordId), $tableName, $recordId)),
            'program_publication_approval' => $this->programPublicationApprovalPayload($this->requireEntity($this->publicationApprovals->find($recordId), $tableName, $recordId)),
            'program_test_execution' => $this->programTestExecutionPayload($this->requireEntity($this->testExecutions->find($recordId), $tableName, $recordId)),
            'import_export_mapping' => $this->importExportMappingPayload($this->requireEntity($this->importExportMappings->find($recordId), $tableName, $recordId)),
            'import_export_mapping_version' => $this->importExportMappingVersionPayload($this->requireEntity($this->importExportMappingVersions->find($recordId), $tableName, $recordId)),
            'import_export_schedule' => $this->importExportSchedulePayload($this->requireEntity($this->importExportSchedules->find($recordId), $tableName, $recordId)),
            'system_option_list' => $this->systemOptionListPayload($this->requireEntity($this->systemOptionLists->find($recordId), $tableName, $recordId)),
            'system_option' => $this->systemOptionPayload($this->requireEntity($this->systemOptions->find($recordId), $tableName, $recordId)),
            'system_parameter' => $this->systemParameterPayload($this->requireEntity($this->systemParameters->find($recordId), $tableName, $recordId)),
            'system_parameter_value' => $this->systemParameterValuePayload($this->requireEntity($this->systemParameterValues->find($recordId), $tableName, $recordId)),
            default => throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_TARGET_UNSUPPORTED', 'Tabela de integridade nao suportada.', 422, [
                'tableName' => $tableName,
                'recordId' => $recordId,
            ]),
        };
    }

    private function requireEntity(mixed $entity, string $tableName, int $recordId): mixed
    {
        if ($entity === null) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_TARGET_NOT_FOUND', 'Registro estrutural nao encontrado para verificacao.', 404, [
                'tableName' => $tableName,
                'recordId' => $recordId,
            ]);
        }

        return $entity;
    }

    private function buildSignature(array $payload): array
    {
        $canonical = $this->canonicalJson($payload);
        $hash = hash('sha256', $canonical);
        $signature = hash_hmac('sha256', $canonical, $this->secret());
        return [$hash, $signature];
    }

    private function canonicalJson(array $payload): string
    {
        return (string) json_encode($this->canonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    private function secret(): string
    {
        return (string) ($_ENV['APP_INTEGRITY_KEY'] ?? $_SERVER['APP_INTEGRITY_KEY'] ?? 'dev-integrity-key');
    }

    private function signedBy(): string
    {
        return (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev');
    }

    private function programPayload(Program $program): array
    {
        return [
            'code' => $program->getCode(),
            'title' => $program->getTitle(),
            'module' => $program->getModule(),
            'programType' => $program->getProgramType(),
            'screenId' => $program->getScreenId(),
            'status' => $program->getStatus(),
            'programOrigin' => $program->getProgramOrigin(),
            'ownerScope' => $program->getOwnerScope(),
            'customizationPolicy' => $program->getCustomizationPolicy(),
            'subscriberId' => $program->getSubscriberId(),
            'baseProgramCode' => $program->getBaseProgramCode(),
            'baseProgramVersionId' => $program->getBaseProgramVersionId(),
            'upgradeFrozen' => $program->isUpgradeFrozen(),
            'frozenReason' => $program->getFrozenReason(),
        ];
    }

    private function programVersionPayload(BuilderProgramVersion $version): array
    {
        return [
            'programCode' => $version->getProgramCode(),
            'programTitle' => $version->getProgramTitle(),
            'module' => $version->getModule(),
            'pageType' => $version->getPageType(),
            'builderEntityCode' => $version->getBuilderEntityCode(),
            'screenId' => $version->getScreenId(),
            'version' => $version->getVersion(),
            'status' => $version->getStatus(),
            'subtitle' => $version->getSubtitle(),
            'icon' => $version->getIcon(),
            'permissionPrefix' => $version->getPermissionPrefix(),
            'allowCreate' => $version->isAllowCreate(),
            'allowUpdate' => $version->isAllowUpdate(),
            'allowDelete' => $version->isAllowDelete(),
            'changeSummary' => $version->getChangeSummary(),
            'programOrigin' => $version->getProgramOrigin(),
            'ownerScope' => $version->getOwnerScope(),
            'customizationPolicy' => $version->getCustomizationPolicy(),
            'subscriberId' => $version->getSubscriberId(),
            'baseProgramCode' => $version->getBaseProgramCode(),
            'baseProgramVersionId' => $version->getBaseProgramVersionId(),
            'upgradeFrozen' => $version->isUpgradeFrozen(),
            'frozenReason' => $version->getFrozenReason(),
            'builderConfig' => $version->getBuilderConfig(),
            'generatedDefinition' => $version->getGeneratedDefinition(),
        ];
    }

    private function apiSourcePayload(BuilderApiSource $source): array
    {
        return [
            'code' => $source->getCode(),
            'name' => $source->getName(),
            'authMode' => $source->getAuthMode(),
            'baseUrl' => $source->getBaseUrl(),
            'openapiUrl' => $source->getOpenapiUrl(),
            'status' => $source->getStatus(),
            'metadata' => $source->getMetadata(),
        ];
    }

    private function builderModulePayload(BuilderModule $module): array
    {
        return [
            'code' => $module->getCode(),
            'name' => $module->getName(),
            'abbreviation' => $module->getAbbreviation(),
            'numberStart' => $module->getNumberStart(),
            'numberEnd' => $module->getNumberEnd(),
            'enabled' => $module->isEnabled(),
            'metadata' => $module->getMetadata(),
        ];
    }

    private function builderEntityPayload(BuilderEntity $entity): array
    {
        $fields = [];
        foreach ($entity->getFields() as $field) {
            $fields[] = [
                'code' => $field->getCode(),
                'label' => $field->getLabel(),
                'dataType' => $field->getDataType(),
                'required' => $field->isRequired(),
                'primaryKey' => $field->isPrimaryKey(),
                'length' => $field->getLength(),
                'precision' => $field->getPrecisionValue(),
                'scale' => $field->getScaleValue(),
                'position' => $field->getPosition(),
                'options' => $field->getOptions(),
            ];
        }
        usort($fields, static fn (array $left, array $right): int => strcmp((string) ($left['code'] ?? ''), (string) ($right['code'] ?? '')));

        return [
            'code' => $entity->getCode(),
            'name' => $entity->getName(),
            'entityType' => $entity->getEntityType(),
            'tableName' => $entity->getTableName(),
            'sourceName' => $entity->getSourceName(),
            'status' => $entity->getStatus(),
            'situationEnabled' => $entity->isSituationEnabled(),
            'situationFieldCode' => $entity->getSituationFieldCode(),
            'metadata' => $entity->getMetadata(),
            'fields' => $fields,
        ];
    }

    private function builderEntitySituationPayload(BuilderEntitySituation $situation): array
    {
        return [
            'builderEntityId' => $situation->getBuilderEntity()?->getId(),
            'builderEntityCode' => $situation->getBuilderEntity()?->getCode(),
            'code' => $situation->getCode(),
            'label' => $situation->getLabel(),
            'description' => $situation->getDescription(),
            'position' => $situation->getPosition(),
            'initial' => $situation->isInitial(),
            'final' => $situation->isFinal(),
            'enabled' => $situation->isEnabled(),
            'metadata' => $situation->getMetadata(),
        ];
    }

    private function builderEntitySituationTransitionPayload(BuilderEntitySituationTransition $transition): array
    {
        return [
            'builderEntityId' => $transition->getBuilderEntity()?->getId(),
            'builderEntityCode' => $transition->getBuilderEntity()?->getCode(),
            'fromCode' => $transition->getFromCode(),
            'toCode' => $transition->getToCode(),
            'actionId' => $transition->getActionId(),
            'label' => $transition->getLabel(),
            'permission' => $transition->getPermission(),
            'position' => $transition->getPosition(),
            'enabled' => $transition->isEnabled(),
            'guardConfig' => $transition->getGuardConfig(),
            'effects' => $transition->getEffects(),
            'metadata' => $transition->getMetadata(),
        ];
    }

    private function builderEntityVersionPayload(BuilderEntityVersion $version): array
    {
        return [
            'builderEntityCode' => $version->getBuilderEntityCode(),
            'entityName' => $version->getEntityName(),
            'entityType' => $version->getEntityType(),
            'tableName' => $version->getTableName(),
            'revision' => $version->getRevision(),
            'status' => $version->getStatus(),
            'action' => $version->getAction(),
            'sourceVersionId' => $version->getSourceVersionId(),
            'snapshot' => $version->getSnapshot(),
            'changeSummary' => $version->getChangeSummary(),
        ];
    }

    private function screenPayload(ScreenDefinition $screen): array
    {
        return [
            'screenId' => $screen->getScreenId(),
            'pageType' => $screen->getPageType(),
            'schemaVersion' => $screen->getSchemaVersion(),
            'definition' => $screen->getDefinition(),
            'status' => $screen->getStatus(),
            'version' => $screen->getVersion(),
        ];
    }

    private function endpointPayload(RuntimeEndpoint $endpoint): array
    {
        return [
            'screenId' => $endpoint->getScreenId(),
            'endpointId' => $endpoint->getEndpointId(),
            'handler' => $endpoint->getHandler(),
            'permission' => $endpoint->getPermission(),
            'enabled' => $endpoint->isEnabled(),
            'config' => $endpoint->getConfig(),
        ];
    }

    private function runtimeLockPolicyPayload(RuntimeLockPolicy $policy): array
    {
        return [
            'tenantId' => $policy->getTenantId(),
            'programId' => $policy->getProgramId(),
            'entityCode' => $policy->getEntityCode(),
            'actionId' => $policy->getActionId(),
            'mode' => $policy->getMode(),
            'stalePolicy' => $policy->getStalePolicy(),
            'lockTtlSeconds' => $policy->getLockTtlSeconds(),
            'heartbeatIntervalSeconds' => $policy->getHeartbeatIntervalSeconds(),
            'enabled' => $policy->isEnabled(),
            'handlerId' => $policy->getHandlerId(),
            'conditionConfig' => $policy->getConditionConfig(),
        ];
    }

    private function overlayPayload(BuilderProgramOverlay $overlay): array
    {
        return [
            'programCode' => $overlay->getProgramCode(),
            'subscriberId' => $overlay->getSubscriberId(),
            'customizationKind' => $overlay->getCustomizationKind(),
            'baseProgramVersionId' => $overlay->getBaseProgramVersionId(),
            'status' => $overlay->getStatus(),
            'upgradeFrozen' => $overlay->isUpgradeFrozen(),
            'frozenReason' => $overlay->getFrozenReason(),
            'overlayConfig' => $overlay->getOverlayConfig(),
            'metadata' => $overlay->getMetadata(),
        ];
    }

    private function overlayVersionPayload(BuilderProgramOverlayVersion $version): array
    {
        return [
            'overlayId' => $version->getOverlay()->getId(),
            'versionNumber' => $version->getVersionNumber(),
            'status' => $version->getStatus(),
            'snapshot' => $version->getSnapshot(),
            'resolvedDefinition' => $version->getResolvedDefinition(),
            'changeSummary' => $version->getChangeSummary(),
            'publishedAt' => $version->getPublishedAt()?->format(DATE_ATOM),
        ];
    }

    private function programChangeRequestPayload(ProgramChangeRequest $request): array
    {
        return [
            'requestCode' => $request->getRequestCode(),
            'programCode' => $request->getProgramCode(),
            'builderEntityCode' => $request->getBuilderEntityCode(),
            'requestedBy' => $request->getRequestedBy(),
            'requestedActions' => $request->getRequestedActions(),
            'reason' => $request->getReason(),
            'status' => $request->getStatus(),
            'approvedBy' => $request->getApprovedBy(),
            'approvedAt' => $request->getApprovedAt()?->format(DATE_ATOM),
            'metadata' => $request->getMetadata(),
        ];
    }

    private function programChangeGrantPayload(ProgramChangeGrant $grant): array
    {
        return [
            'requestId' => $grant->getRequest()?->getId(),
            'requestCode' => $grant->getRequest()?->getRequestCode(),
            'programCode' => $grant->getProgramCode(),
            'builderEntityCode' => $grant->getBuilderEntityCode(),
            'grantedToUserId' => $grant->getGrantedToUserId(),
            'allowedActions' => $grant->getAllowedActions(),
            'status' => $grant->getStatus(),
            'validUntilPublish' => $grant->isValidUntilPublish(),
            'consumedAt' => $grant->getConsumedAt()?->format(DATE_ATOM),
            'metadata' => $grant->getMetadata(),
        ];
    }

    private function programPublicationApprovalPayload(ProgramPublicationApproval $approval): array
    {
        return [
            'programCode' => $approval->getProgramCode(),
            'builderProgramVersionId' => $approval->getBuilderProgramVersionId(),
            'requestedBy' => $approval->getRequestedBy(),
            'approvedBy' => $approval->getApprovedBy(),
            'status' => $approval->getStatus(),
            'testExecutionBundleId' => $approval->getTestExecutionBundleId(),
            'approvedAt' => $approval->getApprovedAt()?->format(DATE_ATOM),
            'metadata' => $approval->getMetadata(),
        ];
    }

    private function programTestExecutionPayload(ProgramTestExecution $testExecution): array
    {
        return [
            'programCode' => $testExecution->getProgramCode(),
            'builderProgramVersionId' => $testExecution->getBuilderProgramVersionId(),
            'builderEntityVersionId' => $testExecution->getBuilderEntityVersionId(),
            'bundleId' => $testExecution->getBundleId(),
            'testPlanId' => $testExecution->getTestPlanId(),
            'executedBy' => $testExecution->getExecutedBy(),
            'status' => $testExecution->getStatus(),
            'checklistSnapshot' => $testExecution->getChecklistSnapshot(),
            'evidences' => $testExecution->getEvidences(),
            'notes' => $testExecution->getNotes(),
            'executedAt' => $testExecution->getExecutedAt()?->format(DATE_ATOM),
        ];
    }

    private function importExportMappingPayload(ImportExportMapping $mapping): array
    {
        return [
            'code' => $mapping->getCode(),
            'name' => $mapping->getName(),
            'direction' => $mapping->getDirection(),
            'targetType' => $mapping->getTargetType(),
            'targetCode' => $mapping->getTargetCode(),
            'format' => $mapping->getFormat(),
            'mapping' => $mapping->getMapping(),
            'status' => $mapping->getStatus(),
        ];
    }

    private function importExportMappingVersionPayload(ImportExportMappingVersion $version): array
    {
        return [
            'mappingCode' => $version->getMappingCode(),
            'versionNumber' => $version->getVersionNumber(),
            'snapshot' => $version->getSnapshot(),
            'changeSummary' => $version->getChangeSummary(),
            'createdBy' => $version->getCreatedBy(),
            'createdAt' => $version->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function importExportSchedulePayload(ImportExportSchedule $schedule): array
    {
        return [
            'code' => $schedule->getCode(),
            'name' => $schedule->getName(),
            'mappingCode' => $schedule->getMappingCode(),
            'frequency' => $schedule->getFrequency(),
            'enabled' => $schedule->isEnabled(),
            'parameters' => $schedule->getParameters(),
            'intervalMinutes' => $schedule->getIntervalMinutes(),
            'dailyHour' => $schedule->getDailyHour(),
            'dailyMinute' => $schedule->getDailyMinute(),
            'lastRunAt' => $schedule->getLastRunAt()?->format(DATE_ATOM),
            'nextRunAt' => $schedule->getNextRunAt()?->format(DATE_ATOM),
            'lastStatus' => $schedule->getLastStatus(),
            'updatedBy' => $schedule->getUpdatedBy(),
        ];
    }

    private function systemOptionListPayload(SystemOptionList $optionList): array
    {
        return [
            'code' => $optionList->getCode(),
            'name' => $optionList->getName(),
            'description' => $optionList->getDescription(),
            'enabled' => $optionList->isEnabled(),
        ];
    }

    private function systemOptionPayload(SystemOption $option): array
    {
        return [
            'optionListId' => $option->getOptionList()?->getId(),
            'optionListCode' => $option->getOptionList()?->getCode(),
            'code' => $option->getCode(),
            'description' => $option->getDescription(),
            'position' => $option->getPosition(),
            'enabled' => $option->isEnabled(),
            'metadata' => $option->getMetadata(),
        ];
    }

    private function systemParameterPayload(SystemParameter $parameter): array
    {
        return [
            'code' => $parameter->getCode(),
            'name' => $parameter->getName(),
            'description' => $parameter->getDescription(),
            'dataType' => $parameter->getDataType(),
            'optionListId' => $parameter->getOptionList()?->getId(),
            'required' => $parameter->isRequired(),
            'defaultValue' => $parameter->getDefaultValue(),
            'enabled' => $parameter->isEnabled(),
        ];
    }

    private function systemParameterValuePayload(SystemParameterValue $parameterValue): array
    {
        return [
            'parameterId' => $parameterValue->getParameter()?->getId(),
            'parameterCode' => $parameterValue->getParameter()?->getCode(),
            'establishmentCode' => $parameterValue->getEstablishmentCode(),
            'startsAt' => $parameterValue->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $parameterValue->getEndsAt()?->format(DATE_ATOM),
            'value' => $parameterValue->getValue(),
            'enabled' => $parameterValue->isEnabled(),
        ];
    }
}
