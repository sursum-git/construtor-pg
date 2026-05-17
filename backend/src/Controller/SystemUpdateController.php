<?php

namespace App\Controller;

use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\CentralControlGuard;
use App\Runtime\SystemUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/system-updates')]
class SystemUpdateController extends AbstractController
{
    public function __construct(
        private readonly SystemUpdateService $updates,
        private readonly RuntimeSessionGuard $sessions,
        private readonly CentralControlGuard $central,
    ) {
    }

    #[Route('/bootstrap', methods: ['GET'])]
    public function bootstrap(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->updates->bootstrap(
                false,
                trim((string) $request->query->get('subscriberCode')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->updates->check(
                trim((string) (($payload['source'] ?? null) ?: '')) ?: null,
                true,
                ($payload['autoQueue'] ?? false) === true,
                trim((string) ($payload['subscriberCode'] ?? '')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/apply', methods: ['POST'])]
    public function apply(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->updates->queueApply(
                trim((string) ($payload['version'] ?? '')),
                ($payload['forceConsent'] ?? false) === true
                ,
                'manual',
                'ui',
                trim((string) ($payload['subscriberCode'] ?? '')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/download', methods: ['POST'])]
    public function download(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->updates->downloadPackage(
                trim((string) ($payload['version'] ?? '')),
                trim((string) ($payload['subscriberCode'] ?? '')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/consent', methods: ['POST'])]
    public function consent(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->updates->registerConsent(
                trim((string) ($payload['version'] ?? '')),
                trim((string) ($payload['status'] ?? 'approved')) ?: 'approved',
                trim((string) ($payload['reason'] ?? '')) ?: null,
                'ui',
                trim((string) ($payload['subscriberCode'] ?? '')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/rollout-plan', methods: ['GET'])]
    public function rolloutPlan(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            return $this->json($this->updates->buildRolloutPlan(
                trim((string) $request->query->get('version')),
                trim((string) $request->query->get('subscriberCode')) ?: null
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/subscriber-log/bootstrap', methods: ['GET'])]
    public function subscriberLogBootstrap(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            return $this->json($this->updates->subscriberLogBootstrap(
                trim((string) $request->query->get('subscriberCode')) ?: null,
                max(1, min(200, $request->query->getInt('limit', 80)))
            ));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/executions', methods: ['GET'])]
    public function executions(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            return $this->json($this->updates->listExecutionHistory([
                'subscriberCode' => trim((string) $request->query->get('subscriberCode')) ?: null,
                'status' => trim((string) $request->query->get('status')),
                'category' => trim((string) $request->query->get('category')),
                'dateFrom' => trim((string) $request->query->get('dateFrom')),
                'dateTo' => trim((string) $request->query->get('dateTo')),
                'limit' => max(1, min(300, $request->query->getInt('limit', 120))),
            ]));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/jobs/{jobId}', methods: ['GET'])]
    public function job(int $jobId): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            return $this->json($this->updates->getJob($jobId));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/jobs/{jobId}/events', methods: ['GET'])]
    public function jobEvents(int $jobId, Request $request): StreamedResponse|JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $maxSeconds = max(5, min(180, (int) $request->query->get('timeout', 120)));
            $intervalSeconds = max(1, min(5, (int) $request->query->get('interval', 2)));

            $response = new StreamedResponse(function () use ($jobId, $maxSeconds, $intervalSeconds): void {
                @set_time_limit(0);
                $startedAt = time();
                $lastFingerprint = '';

                echo "retry: 2000\n\n";
                $this->flushStream();

                while ((time() - $startedAt) < $maxSeconds) {
                    if (connection_aborted()) {
                        break;
                    }
                    $job = $this->updates->getJob($jobId);
                    $fingerprint = md5(json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    if ($fingerprint !== $lastFingerprint) {
                        $lastFingerprint = $fingerprint;
                        $this->writeSse('system-update-job', $job);
                    }
                    if (in_array((string) ($job['status'] ?? ''), ['succeeded', 'failed'], true)) {
                        break;
                    }
                    sleep($intervalSeconds);
                }
            });

            $response->headers->set('Content-Type', 'text/event-stream; charset=UTF-8');
            $response->headers->set('Cache-Control', 'no-cache, no-transform');
            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Connection', 'keep-alive');
            return $response;
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
                'code' => 'SYSTEM_UPDATE_ERROR',
                'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha na rotina de atualizacao.',
                'details' => ['exception' => $error::class],
            ],
        ], 500);
    }

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
}
