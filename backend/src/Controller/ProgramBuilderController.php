<?php

namespace App\Controller;

use App\Builder\ProgramBuilderService;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/program-builder')]
class ProgramBuilderController extends AbstractController
{
    public function __construct(
        private readonly ProgramBuilderService $builder,
        private readonly RuntimeSessionGuard $sessions,
    ) {
    }

    #[Route('/bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->bootstrap());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/programs/{programCode}', methods: ['GET'])]
    public function program(string $programCode): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->getProgram($programCode));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entities/{entityCode}', methods: ['GET'])]
    public function entity(string $entityCode): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->getEntity($entityCode));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entities', methods: ['POST'])]
    public function saveEntity(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveEntity(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/modules', methods: ['POST'])]
    public function saveModule(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveModule(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/entity-versions/{id}/restore', methods: ['POST'])]
    public function restoreEntityVersion(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->restoreEntityVersion($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/drafts', methods: ['POST'])]
    public function saveDraft(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->builder->saveDraft(is_array($payload) ? $payload : []));
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
            return $this->json($this->builder->previewDraft(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/versions/{id}/publish', methods: ['POST'])]
    public function publish(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->publishVersion($id));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/versions/{id}/duplicate', methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->builder->duplicateVersion($id));
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

        if ($error instanceof TableNotFoundException) {
            return $this->json([
                'error' => [
                    'code' => 'PROGRAM_BUILDER_STORAGE_NOT_READY',
                    'message' => 'Estrutura do construtor de programas ainda nao foi criada no banco.',
                    'details' => [
                        'hint' => 'Execute as migrations pendentes do backend.',
                        'exception' => $error::class,
                    ],
                ],
            ], 503);
        }

        return $this->json([
            'error' => [
                'code' => 'PROGRAM_BUILDER_ERROR',
                'message' => 'Erro interno no construtor de programas.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }
}
