<?php

namespace App\Controller;

use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\SystemUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/runtime/system-updates')]
class RuntimeSystemUpdateController extends AbstractController
{
    public function __construct(
        private readonly RuntimeSessionGuard $sessions,
        private readonly SystemUpdateService $updates,
    ) {
    }

    #[Route('/summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->updates->runtimeSummary(true));
        } catch (\Throwable $error) {
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
                    'code' => 'RUNTIME_SYSTEM_UPDATE_SUMMARY_ERROR',
                    'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha ao consultar atualizacoes pendentes.',
                ],
            ], 500);
        }
    }

    #[Route('/check-now', methods: ['POST'])]
    public function checkNow(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->updates->runtimeSummary(true));
        } catch (\Throwable $error) {
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
                    'code' => 'RUNTIME_SYSTEM_UPDATE_CHECK_ERROR',
                    'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha ao verificar atualizacoes.',
                ],
            ], 500);
        }
    }

    #[Route('/run-pending', methods: ['POST'])]
    public function runPending(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->updates->runPendingRuntime(true));
        } catch (\Throwable $error) {
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
                    'code' => 'RUNTIME_SYSTEM_UPDATE_RUN_ERROR',
                    'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha ao aplicar atualizacoes locais.',
                ],
            ], 500);
        }
    }
}
