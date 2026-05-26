<?php

namespace App\Controller;

use App\Install\SystemInstallService;
use App\Runtime\RuntimeHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/install')]
class SystemInstallController extends AbstractController
{
    public function __construct(
        private readonly SystemInstallService $installer,
    ) {
    }

    #[Route('/status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        try {
            return $this->json($this->installer->status());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/precheck', methods: ['POST'])]
    public function precheck(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

            return $this->json($this->installer->precheck(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

            return $this->json($this->installer->run(is_array($payload) ? $payload : []));
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
                'code' => 'SYSTEM_INSTALL_ERROR',
                'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha na instalacao do sistema.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
