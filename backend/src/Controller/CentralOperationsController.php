<?php

namespace App\Controller;

use App\Runtime\CentralControlGuard;
use App\Runtime\CentralOperationsService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/central-operations')]
class CentralOperationsController extends AbstractController
{
    public function __construct(
        private readonly CentralOperationsService $operations,
        private readonly RuntimeSessionGuard $sessions,
        private readonly CentralControlGuard $central,
    ) {
    }

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();

            return $this->json($this->operations->dashboard());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/license-action', methods: ['POST'])]
    public function licenseAction(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();

            return $this->json($this->operations->licenseAction($this->payload($request)));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/token-action', methods: ['POST'])]
    public function tokenAction(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();

            return $this->json($this->operations->tokenAction($this->payload($request)));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : [];
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
                'code' => 'CENTRAL_OPERATIONS_ERROR',
                'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha nas operacoes da central.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
