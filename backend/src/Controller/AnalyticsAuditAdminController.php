<?php

namespace App\Controller;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAnalyticsAuditAdminService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/analytics-audit')]
class AnalyticsAuditAdminController extends AbstractController
{
    public function __construct(
        private readonly RuntimeAnalyticsAuditAdminService $audit,
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

            return $this->json($this->audit->bootstrap());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entries', methods: ['GET'])]
    public function entries(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json($this->audit->listEntries([
                'tenantId' => trim((string) $request->query->get('tenantId')),
                'userId' => trim((string) $request->query->get('userId')),
                'screenId' => trim((string) $request->query->get('screenId')),
                'datasetId' => trim((string) $request->query->get('datasetId')),
                'resultSource' => trim((string) $request->query->get('resultSource')),
                'dateFrom' => trim((string) $request->query->get('dateFrom')),
                'dateTo' => trim((string) $request->query->get('dateTo')),
                'limit' => max(1, min(300, $request->query->getInt('limit', 120))),
            ]));
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
                'code' => 'ANALYTICS_AUDIT_ADMIN_ERROR',
                'message' => $error->getMessage() ?: 'Falha ao consultar auditoria analytics.',
            ],
        ], 500);
    }
}
