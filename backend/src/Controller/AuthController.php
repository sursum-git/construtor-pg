<?php

namespace App\Controller;

use App\Auth\AuthService;
use App\Runtime\RuntimeHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $auth,
    ) {
    }

    #[Route('/api/auth/providers', name: 'auth_providers', methods: ['GET'])]
    public function providers(): JsonResponse
    {
        try {
            return $this->json($this->auth->listProviders());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $this->json($this->auth->login($payload, $request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            return $this->json($this->auth->logout($request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/select-subscriber', name: 'auth_select_subscriber', methods: ['POST'])]
    public function selectSubscriber(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $this->json($this->auth->selectSubscriber($payload, $request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/password/request-reset', name: 'auth_password_request_reset', methods: ['POST'])]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $this->json($this->auth->requestPasswordReset($payload, $request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/password/reset', name: 'auth_password_reset', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $this->json($this->auth->resetPassword($payload));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/remember', name: 'auth_remember', methods: ['POST'])]
    public function remember(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $this->json($this->auth->remember($payload, $request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/session', name: 'auth_session', methods: ['GET'])]
    public function session(Request $request): JsonResponse
    {
        try {
            return $this->json($this->auth->currentSession($request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/oauth/{providerCode}/start', name: 'auth_oauth_start', methods: ['GET'])]
    public function oauthStart(string $providerCode, Request $request): JsonResponse
    {
        try {
            return $this->json($this->auth->startOAuth($providerCode, $request));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/auth/oauth/{providerCode}/callback', name: 'auth_oauth_callback', methods: ['GET'])]
    public function oauthCallback(string $providerCode, Request $request): JsonResponse
    {
        try {
            return $this->json($this->auth->completeOAuth($providerCode, $request));
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
                'code' => 'AUTH_ERROR',
                'message' => 'Erro interno na autenticacao.',
                'details' => [
                    'exception' => $error::class,
                ],
            ],
        ], 500);
    }
}
