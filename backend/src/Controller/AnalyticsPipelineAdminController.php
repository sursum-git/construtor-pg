<?php

namespace App\Controller;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAnalyticsPipelineAdminService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/analytics-pipelines')]
class AnalyticsPipelineAdminController extends AbstractController
{
    public function __construct(
        private readonly RuntimeAnalyticsPipelineAdminService $admin,
        private readonly RuntimeSessionGuard $sessions,
        private readonly PermissionResolver $permissions,
    ) {
    }

    #[Route('/bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->bootstrap());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/rows', methods: ['GET'])]
    public function rows(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->listRows());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureWritePermission();
            $payload = $request->toArray();

            return $this->json($this->admin->run(
                trim((string) ($payload['screenId'] ?? '')),
                trim((string) ($payload['pipelineId'] ?? '')),
                ($payload['sync'] ?? true) !== false
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/publish', methods: ['POST'])]
    public function publish(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureWritePermission();
            $payload = $request->toArray();

            return $this->json($this->admin->publish(
                trim((string) ($payload['screenId'] ?? '')),
                trim((string) ($payload['pipelineId'] ?? '')),
                trim((string) ($payload['executionId'] ?? '')),
                ($payload['strictCompatibility'] ?? false) === true
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->status(
                trim((string) $request->query->get('screenId', '')),
                trim((string) $request->query->get('pipelineId', ''))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/logs', methods: ['GET'])]
    public function logs(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->logs(
                trim((string) $request->query->get('screenId', '')),
                trim((string) $request->query->get('pipelineId', ''))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/versions', methods: ['GET'])]
    public function versions(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->versions(
                trim((string) $request->query->get('screenId', '')),
                trim((string) $request->query->get('pipelineId', ''))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/impact', methods: ['GET'])]
    public function impact(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->admin->impact(
                trim((string) $request->query->get('screenId', '')),
                trim((string) $request->query->get('pipelineId', ''))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/rollback', methods: ['POST'])]
    public function rollback(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureWritePermission();
            $payload = $request->toArray();

            return $this->json($this->admin->rollback(
                trim((string) ($payload['screenId'] ?? '')),
                trim((string) ($payload['pipelineId'] ?? '')),
                max(1, (int) ($payload['versionNo'] ?? 0))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    private function ensureReadPermission(): void
    {
        if ($this->permissions->hasPermission('admin.read')) {
            return;
        }

        throw new RuntimeHttpException('RUNTIME_ACCESS_DENIED', 'Acesso negado.', 403);
    }

    private function ensureWritePermission(): void
    {
        if ($this->permissions->hasPermission('admin.write') || $this->permissions->hasPermission('admin.read')) {
            return;
        }

        throw new RuntimeHttpException('RUNTIME_ACCESS_DENIED', 'Acesso negado.', 403);
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

        return $this->json([
            'error' => [
                'code' => 'ANALYTICS_PIPELINE_ADMIN_ERROR',
                'message' => $error->getMessage() ?: 'Falha ao consultar pipelines analytics.',
            ],
        ], 500);
    }
}
