<?php

namespace App\Controller;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeRegulatedDocumentAdminService;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/regulated-document')]
class RegulatedDocumentAdminController extends AbstractController
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentAdminService $documents,
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

            return $this->json($this->documents->bootstrap());
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

            return $this->json($this->documents->listEntries([
                'tenantId' => trim((string) $request->query->get('tenantId')),
                'userId' => trim((string) $request->query->get('userId')),
                'screenId' => trim((string) $request->query->get('screenId')),
                'track' => trim((string) $request->query->get('track')),
                'documentType' => trim((string) $request->query->get('documentType')),
                'state' => trim((string) $request->query->get('state')),
                'dateFrom' => trim((string) $request->query->get('dateFrom')),
                'dateTo' => trim((string) $request->query->get('dateTo')),
                'limit' => max(1, min(300, $request->query->getInt('limit', 120))),
            ]));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/artifact', methods: ['GET'])]
    public function artifact(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureArtifactPermission();

            return $this->json($this->documents->artifact(trim((string) $request->query->get('issueId'))));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureReadPermission();

            return $this->json([
                'items' => $this->documents->events(trim((string) $request->query->get('issueId'))),
            ]);
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->ensureArtifactPermission();
            $payload = json_decode($request->getContent(), true);

            return $this->json($this->documents->verifyIssue(trim((string) ($payload['issueId'] ?? ''))));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    private function ensureReadPermission(): void
    {
        if ($this->permissions->hasAnyPermission(['regulated_document.admin.read', 'admin.read'])) {
            return;
        }

        throw new RuntimeHttpException('RUNTIME_ACCESS_DENIED', 'Acesso negado.', 403);
    }

    private function ensureArtifactPermission(): void
    {
        if ($this->permissions->hasAnyPermission(['regulated_document.admin.artifact', 'regulated_document.admin.read', 'admin.read'])) {
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
                'code' => 'REGULATED_DOCUMENT_ADMIN_ERROR',
                'message' => $error->getMessage() ?: 'Falha ao consultar documentos regulados.',
            ],
        ], 500);
    }
}
