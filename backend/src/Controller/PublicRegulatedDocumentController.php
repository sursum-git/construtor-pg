<?php

namespace App\Controller;

use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeRegulatedDocumentAuthenticityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/regulated-document')]
class PublicRegulatedDocumentController extends AbstractController
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentAuthenticityService $documents,
    ) {
    }

    #[Route('/verify', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            return $this->json($this->documents->verify((string) $request->query->get('hash', '')));
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
                    'code' => 'REGULATED_DOCUMENT_PUBLIC_VERIFY_ERROR',
                    'message' => $error->getMessage() ?: 'Falha ao conferir o documento regulado.',
                ],
            ], 500);
        }
    }
}
