<?php

namespace App\Controller;

use App\ImportExport\ImportExportMappingService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/import-export-mappings')]
class ImportExportMappingController extends AbstractController
{
    public function __construct(
        private readonly ImportExportMappingService $mappings,
        private readonly RuntimeSessionGuard $sessions,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->mappings->list());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/{code}', methods: ['GET'])]
    public function getMapping(string $code): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->mappings->get($code));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->mappings->save(is_array($payload) ? $payload : []));
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
            return $this->json($this->mappings->preview(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->mappings->execute(is_array($payload) ? $payload : []));
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

        return $this->json([
            'error' => [
                'code' => 'IMPORT_EXPORT_ERROR',
                'message' => 'Erro interno na importacao/exportacao.',
                'details' => [
                    'exception' => $error::class,
                ],
            ],
        ], 500);
    }
}
