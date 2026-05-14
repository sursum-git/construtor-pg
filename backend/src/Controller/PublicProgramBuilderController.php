<?php

namespace App\Controller;

use App\Builder\ExternalBuilderContextService;
use App\Runtime\RuntimeHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/program-builder')]
class PublicProgramBuilderController extends AbstractController
{
    public function __construct(
        private readonly ExternalBuilderContextService $context,
    ) {
    }

    #[Route('/external-context', methods: ['GET'])]
    public function externalContext(Request $request): JsonResponse
    {
        try {
            return $this->json($this->context->getPublicContext(
                $request->headers->get('X-Builder-Public-Key'),
                [
                    'remoteAddr' => $request->getClientIp(),
                    'userAgent' => $request->headers->get('User-Agent'),
                ]
            ));
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
                'code' => 'BUILDER_PUBLIC_CONTEXT_ERROR',
                'message' => 'Erro interno ao montar o contexto publico do construtor.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
