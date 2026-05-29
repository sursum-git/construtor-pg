<?php

namespace App\Controller;

use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeReportAuthenticityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/report-authenticity')]
class PublicReportAuthenticityController extends AbstractController
{
    public function __construct(
        private readonly RuntimeReportAuthenticityService $authenticity,
    ) {
    }

    #[Route('/verify', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            return $this->json($this->authenticity->verify((string) $request->query->get('hash', '')));
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
                'code' => 'REPORT_AUTHENTICITY_VERIFY_ERROR',
                'message' => 'Falha ao conferir a autenticidade do relatorio.',
            ],
        ], 500);
    }
}
