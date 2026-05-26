<?php

namespace App\Controller;

use App\Install\InstallerActivationCenterService;
use App\Runtime\RuntimeHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/installer/activation')]
class InstallerActivationController extends AbstractController
{
    public function __construct(
        private readonly InstallerActivationCenterService $activation,
    ) {
    }

    #[Route('/request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        try {
            return $this->json($this->activation->request($this->payload($request)));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        try {
            return $this->json($this->activation->confirm($this->payload($request)));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/service', methods: ['POST'])]
    public function service(Request $request): JsonResponse
    {
        try {
            return $this->json($this->activation->service($this->payload($request), (string) $request->headers->get('Authorization', '')));
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
                'code' => 'INSTALLER_ACTIVATION_ERROR',
                'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha na ativacao do instalador.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
