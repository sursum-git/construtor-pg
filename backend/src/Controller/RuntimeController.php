<?php

namespace App\Controller;

use App\Runtime\RuntimeEndpointDispatcher;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeLiteralService;
use App\Runtime\RuntimeMessageService;
use App\Runtime\RuntimeProcessHandler;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\RuntimeValidationException;
use App\Runtime\ScreenDefinitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class RuntimeController extends AbstractController
{
    public function __construct(
        private readonly ScreenDefinitionService $screens,
        private readonly RuntimeEndpointDispatcher $dispatcher,
        private readonly RuntimeMessageService $messages,
        private readonly RuntimeLiteralService $literals,
        private readonly RuntimeProcessHandler $process,
        private readonly RuntimeSessionGuard $sessions,
    ) {
    }

    #[Route('/api/runtime/screens/{screenId}', name: 'runtime_screen_definition', methods: ['GET'])]
    public function screen(string $screenId): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->screens->getDefinition($screenId));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/runtime/screens/{screenId}/endpoints/{endpointId}', name: 'runtime_endpoint', methods: ['POST'])]
    public function endpoint(string $screenId, string $endpointId, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                $payload = [];
            }
            return $this->json($this->dispatcher->dispatch($screenId, $endpointId, $payload));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/runtime/screens/{screenId}/documents/{documentId}', name: 'runtime_document', methods: ['GET'])]
    public function document(string $screenId, string $documentId, Request $request): Response
    {
        try {
            $this->sessions->ensureActive();
            if ($screenId === 'processamento.relatorio-clientes' && $documentId === 'resultado') {
                $jobId = (int) $request->query->get('jobId', 0);

                return new Response(
                    $this->process->renderClientesProcessDocument($jobId),
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            }
            $safeScreen = htmlspecialchars($screenId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeDocument = htmlspecialchars($documentId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return new Response(
                '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Logs</title></head>'
                . '<body><h1>Logs</h1><p>Documento autorizado pelo runtime.</p>'
                . '<dl><dt>Tela</dt><dd>' . $safeScreen . '</dd><dt>Documento</dt><dd>' . $safeDocument . '</dd></dl>'
                . '</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/api/runtime/events', name: 'runtime_events', methods: ['GET'])]
    public function events(Request $request): StreamedResponse
    {
        $maxSeconds = max(1, min(60, (int) $request->query->get('timeout', 45)));
        $intervalSeconds = max(1, min(10, (int) $request->query->get('interval', 5)));

        $response = new StreamedResponse(function () use ($maxSeconds, $intervalSeconds): void {
            @set_time_limit(0);
            $startedAt = time();
            $lastKeepAliveAt = 0;

            echo "retry: 5000\n\n";
            $this->flushStream();
            $this->sessions->ensureActive();

            while ((time() - $startedAt) < $maxSeconds) {
                if (connection_aborted()) {
                    break;
                }

                try {
                    $payload = $this->messages->poll(true);
                    if (!empty($payload['messages'])) {
                        $this->writeSse('runtime-messages', $payload);
                    } else {
                        $this->sessions->ensureActive(false);
                    }
                } catch (\Throwable $error) {
                    $this->writeSse('runtime-error', [
                        'error' => $this->formatError($error),
                    ]);
                    break;
                }

                if ((time() - $lastKeepAliveAt) >= 15) {
                    echo ": keepalive\n\n";
                    $lastKeepAliveAt = time();
                    $this->flushStream();
                }

                sleep($intervalSeconds);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    #[Route('/api/runtime/literals/{locale}', name: 'runtime_literals', methods: ['GET'])]
    public function literals(string $locale): JsonResponse
    {
        try {
            $this->sessions->ensureActive();

            return $this->json($this->literals->bundle($locale));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    private function error(\Throwable $error): JsonResponse
    {
        if ($error instanceof RuntimeHttpException) {
            $payload = [
                'error' => [
                    'code' => $error->getErrorCode(),
                    'message' => $error->getMessage(),
                    'details' => $error->getDetails(),
                ],
            ];
            if ($error instanceof RuntimeValidationException) {
                $payload['error']['severity'] = $error->getSeverity();
                $payload['validation'] = $error->getValidation();
                $payload['effects'] = $error->getEffects();
            }

            return $this->json($payload, $error->getStatusCode());
        }

        return $this->json([
            'error' => [
                'code' => 'RUNTIME_ERROR',
                'message' => 'Erro interno no runtime.',
                'details' => [
                    'exception' => $error::class,
                ],
            ],
        ], 500);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeSse(string $event, array $payload): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushStream();
    }

    private function flushStream(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    /**
     * @return array{code: string, message: string, details: array<string, mixed>}
     */
    private function formatError(\Throwable $error): array
    {
        if ($error instanceof RuntimeHttpException) {
            return [
                'code' => $error->getErrorCode(),
                'message' => $error->getMessage(),
                'details' => $error->getDetails(),
            ];
        }

        return [
            'code' => 'RUNTIME_EVENT_ERROR',
            'message' => 'Erro interno no canal de eventos.',
            'details' => [
                'exception' => $error::class,
            ],
        ];
    }
}
