<?php

namespace App\Controller;

use App\Provisioning\SubscriberProvisioningService;
use App\Runtime\CentralControlGuard;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/subscriber-provisioning')]
class SubscriberProvisioningController extends AbstractController
{
    public function __construct(
        private readonly SubscriberProvisioningService $provisioning,
        private readonly RuntimeSessionGuard $sessions,
        private readonly CentralControlGuard $central,
    ) {
    }

    #[Route('/bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            return $this->json($this->provisioning->bootstrap());
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/subscribers', methods: ['POST'])]
    public function saveSubscriber(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->provisioning->saveSubscriber(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/jobs', methods: ['GET'])]
    public function jobs(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            return $this->json([
                'items' => $this->provisioning->listProvisionJobs(
                    trim((string) $request->query->get('subscriberCode')),
                    max(1, min(100, $request->query->getInt('limit', 20)))
                ),
            ]);
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/precheck', methods: ['POST'])]
    public function precheck(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->provisioning->precheckProvision(is_array($payload) ? $payload : []));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/provision', methods: ['POST'])]
    public function provision(Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->provisioning->queueProvision(is_array($payload) ? $payload : []));
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
            return $this->json($this->provisioning->getProvisionJob($jobId));
        } catch (\Throwable $error) {
            return $this->error($error);
        }
    }

    #[Route('/jobs/{jobId}/retry', methods: ['POST'])]
    public function retryJob(int $jobId, Request $request): JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            return $this->json($this->provisioning->retryProvisionJob($jobId, trim((string) (($payload['retryFromStep'] ?? '')))));
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

                    $job = $this->provisioning->getProvisionJob($jobId);
                    $fingerprint = md5(json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    if ($fingerprint !== $lastFingerprint) {
                        $lastFingerprint = $fingerprint;
                        $this->writeSse('subscriber-provisioning-job', $job);
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

    #[Route('/onprem-package', methods: ['GET'])]
    public function onPremPackage(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $this->sessions->ensureActive();
            $this->central->ensureCentral();
            $package = $this->provisioning->buildOnPremPackage([
                'subscriberCode' => $request->query->get('subscriberCode'),
                'databaseEnvironment' => $request->query->get('databaseEnvironment'),
                'databaseIdentity' => $request->query->get('databaseIdentity'),
                'databaseName' => $request->query->get('databaseName'),
                'adminUsername' => $request->query->get('adminUsername'),
                'adminDisplayName' => $request->query->get('adminDisplayName'),
                'instanceCode' => $request->query->get('instanceCode'),
            ]);

            if ($request->query->getBoolean('metadataOnly')) {
                return $this->json($package);
            }

            $response = $this->file($package['path'], $package['fileName']);
            $response->headers->set('X-Construtor-Package-Sha256', (string) ($package['sha256'] ?? ''));
            if (($package['signature'] ?? null) !== null) {
                $response->headers->set('X-Construtor-Package-Signature', (string) $package['signature']);
            }

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
                'code' => 'SUBSCRIBER_PROVISIONING_ERROR',
                'message' => $error->getMessage() !== '' ? $error->getMessage() : 'Falha na operacao de provisionamento.',
                'details' => [
                    'exception' => $error::class,
                ],
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
