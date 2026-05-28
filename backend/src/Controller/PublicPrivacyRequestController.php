<?php

namespace App\Controller;

use App\Privacy\PrivacySubjectRequestService;
use App\Runtime\RuntimeHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/privacy/requests')]
class PublicPrivacyRequestController extends AbstractController
{
    public function __construct(private readonly PrivacySubjectRequestService $requests)
    {
    }

    #[Route('/start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $payload = is_array($payload) ? $payload : [];
            $payload['_request'] = [
                'remoteAddr' => $request->getClientIp(),
                'userAgent' => $request->headers->get('User-Agent'),
            ];

            return $this->json($this->requests->startPublicRequest($payload));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

            return $this->json($this->requests->confirmPublicRequest(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/{protocol}', methods: ['GET'])]
    public function status(string $protocol, Request $request): JsonResponse
    {
        try {
            return $this->json($this->requests->publicStatus($protocol, (string) $request->query->get('email', '')));
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
                'code' => 'PRIVACY_REQUEST_ERROR',
                'message' => 'Erro interno ao processar a solicitacao LGPD.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
