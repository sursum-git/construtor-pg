<?php

namespace App\Controller;

use App\Builder\BuilderAiService;
use App\Builder\BuilderAiSettingsService;
use App\Builder\ProgramBuilderService;
use App\Builder\ExternalBuilderImportService;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/program-builder')]
class ProgramBuilderController extends AbstractController
{
    public function __construct(
        private readonly ProgramBuilderService $builder,
        private readonly ExternalBuilderImportService $externalImport,
        private readonly BuilderAiSettingsService $aiSettings,
        private readonly BuilderAiService $ai,
        private readonly RuntimeSessionGuard $sessions,
    ) {
    }

    #[Route('/bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->bootstrap());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/database/tables', methods: ['GET'])]
    public function databaseTables(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->listDatabaseTables([
                'filter' => $request->query->get('filter'),
                'limit' => $request->query->getInt('limit', 200),
            ]));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/database/inspect', methods: ['POST'])]
    public function inspectDatabaseTable(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->inspectDatabaseTable(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/database/import', methods: ['POST'])]
    public function importDatabaseTable(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->importDatabaseTable(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api-sources/{apiSourceCode}', methods: ['GET'])]
    public function apiSource(string $apiSourceCode): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->getApiSource($apiSourceCode));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api-sources', methods: ['POST'])]
    public function saveApiSource(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveApiSource(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api-sources/import-openapi', methods: ['POST'])]
    public function importOpenApi(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->importOpenApi(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api-sources/odoo/test-connection', methods: ['POST'])]
    public function testOdooConnection(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->testOdooConnection(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api-sources/odoo/model-metadata', methods: ['POST'])]
    public function readOdooModelMetadata(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->readOdooModelMetadata(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/external/validate', methods: ['POST'])]
    public function validateExternalDraft(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->externalImport->validate(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/settings', methods: ['GET'])]
    public function aiSettings(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->aiSettings->getUiSettings());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/settings', methods: ['POST'])]
    public function saveAiSettings(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->aiSettings->saveUiSettings(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/session', methods: ['POST'])]
    public function startAiSession(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->ai->startSession(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/message', methods: ['POST'])]
    public function sendAiMessage(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->ai->sendMessage(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/transcribe', methods: ['POST'])]
    public function transcribeAiMessage(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->ai->transcribe(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/ai/finalize-draft', methods: ['POST'])]
    public function finalizeAiDraft(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->ai->finalizeDraft(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/programs/{programCode}', methods: ['GET'])]
    public function program(string $programCode): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->getProgram($programCode));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entities/{entityCode}', methods: ['GET'])]
    public function entity(string $entityCode): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->getEntity($entityCode));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entities', methods: ['POST'])]
    public function saveEntity(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveEntity(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/modules', methods: ['POST'])]
    public function saveModule(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveModule(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entity-versions/{id}/restore', methods: ['POST'])]
    public function restoreEntityVersion(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->restoreEntityVersion($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/locks/acquire', methods: ['POST'])]
    public function acquireLock(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->acquireEditorLock(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/locks/heartbeat', methods: ['POST'])]
    public function heartbeatLock(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->heartbeatEditorLock(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/locks/release', methods: ['POST'])]
    public function releaseLock(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->releaseEditorLock(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/drafts', methods: ['POST'])]
    public function saveDraft(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveDraft(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->previewDraft(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/versions/{id}/publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->publishVersion($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/versions/{id}/duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->duplicateVersion($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/requests', methods: ['POST'])]
    public function createGovernanceRequest(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->createGovernanceRequest(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/grants', methods: ['POST'])]
    public function approveGovernanceRequest(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->approveGovernanceRequest(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/grants/status', methods: ['POST'])]
    public function changeGovernanceGrantStatus(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->changeGovernanceGrantStatus(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/tests', methods: ['POST'])]
    public function registerGovernanceTest(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->registerGovernanceTest(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/approvals', methods: ['POST'])]
    public function approveGovernancePublication(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->approveGovernancePublication(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/dashboard', methods: ['GET'])]
    public function governanceDashboard(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->governanceDashboard([
                'programCode' => $request->query->get('programCode'),
                'builderProgramVersionId' => $request->query->getInt('builderProgramVersionId', 0),
            ]));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/retention', methods: ['GET'])]
    public function governanceRetention(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->governanceRetentionPolicy());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/governance/retention', methods: ['POST'])]
    public function updateGovernanceRetention(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->updateGovernanceRetentionPolicy(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/overlays/{id}/rebase-preview', methods: ['GET'])]
    public function previewOverlayRebase(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->previewOverlayRebase($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/overlay-versions/{id}/rebase', methods: ['POST'])]
    public function rebaseOverlayVersion(int $id, Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->rebaseOverlayVersion($id, is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/integrity/resign', methods: ['POST'])]
    public function resignIntegrityRecord(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->resignIntegrityRecord(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    private function error(\Throwable $error): JsonResponse
    {
        if ($error instanceof RuntimeHttpException) {
            return $this->json([
                'error' => [
                    'code' => $error->getErrorCode(),
                    'message' => $error->getMessage(),
                    'details' => $error->getDetails(),
                ],
            ], $error->getStatusCode());
        }

        if ($error instanceof TableNotFoundException) {
            return $this->json([
                'error' => [
                    'code' => 'PROGRAM_BUILDER_STORAGE_NOT_READY',
                    'message' => 'Estrutura do construtor de programas ainda nao foi criada no banco.',
                    'details' => [
                        'hint' => 'Execute as migrations pendentes do backend.',
                        'exception' => $error::class,
                    ],
                ],
            ], 503);
        }

        return $this->json([
            'error' => [
                'code' => 'PROGRAM_BUILDER_ERROR',
                'message' => 'Erro interno no construtor de programas.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
