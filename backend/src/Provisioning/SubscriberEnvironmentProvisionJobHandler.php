<?php

namespace App\Provisioning;

use App\Entity\RuntimeAsyncJob;
use App\Runtime\RuntimeJobHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class SubscriberEnvironmentProvisionJobHandler implements RuntimeJobHandlerInterface
{
    private const STEP_ORDER = [
        'prepare_env',
        'start_database',
        'bootstrap_app',
        'create_subscriber',
        'publish_defaults',
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProvisioningSecretStore $secretStore,
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'subscriber.environment.provision';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $projectRoot = dirname($this->kernel->getProjectDir());
        $backendDir = $this->kernel->getProjectDir();
        $script = $this->resolveScript($projectRoot);

        $steps = is_array($payload['steps'] ?? null) && $payload['steps'] !== [] ? $payload['steps'] : $this->initialSteps((string) ($payload['retryFromStep'] ?? ''));
        $retryFromStep = (string) ($payload['retryFromStep'] ?? '');
        $credentials = [];
        if (trim((string) ($payload['credentialRef'] ?? '')) !== '') {
            $credentials = $this->secretStore->load((string) $payload['credentialRef']);
        }
        $stepPayload = array_merge($payload, $credentials);
        $output = '';
        $report = [
            'subscriberCode' => (string) ($payload['subscriberCode'] ?? ''),
            'databaseIdentity' => (string) ($payload['databaseIdentity'] ?? ''),
            'databaseName' => (string) ($payload['databaseName'] ?? ''),
            'deploymentMode' => (string) ($payload['deploymentMode'] ?? ''),
            'runtimeEnvironmentCode' => (string) ($payload['runtimeEnvironmentCode'] ?? ''),
            'retryFromStep' => $retryFromStep !== '' ? $retryFromStep : null,
            'retryJobId' => $payload['retryJobId'] ?? null,
            'script' => basename($script),
            'startedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->touchProgress($job, 'preparing', 'Preparando provisionamento do assinante.', [
            'steps' => $steps,
            'report' => $report,
        ]);

        foreach (self::STEP_ORDER as $stepCode) {
            $currentStatus = $this->readStepStatus($steps, $stepCode);
            if ($currentStatus === 'reused') {
                continue;
            }

            $steps = $this->markStep($steps, $stepCode, 'running');
            $this->touchProgress($job, 'running', 'Executando etapa: ' . $stepCode . '.', [
                'currentStep' => $stepCode,
                'steps' => $steps,
                'report' => $report,
                'outputTail' => $this->tail($output),
            ]);

            $stepStartedAt = microtime(true);
            $process = new Process($this->buildCommand($script, $backendDir, $stepPayload, $stepCode), $projectRoot, $this->buildProcessEnvironment($stepPayload), null, 7200);
            $stepOutput = '';
            $stepErrorOutput = '';
            $process->run(function (string $type, string $buffer) use (&$stepOutput, &$stepErrorOutput, &$output, $job, &$steps, $stepCode, &$report): void {
                if ($type === Process::ERR) {
                    $stepErrorOutput .= $buffer;
                } else {
                    $stepOutput .= $buffer;
                }
                $output .= $buffer;
                $this->touchProgress($job, 'running', 'Executando etapa: ' . $stepCode . '.', [
                    'currentStep' => $stepCode,
                    'steps' => $steps,
                    'report' => $report,
                    'outputTail' => $this->tail($output . "\n" . $stepErrorOutput),
                ]);
            });

            if (!$process->isSuccessful()) {
                $steps = $this->markStep($steps, $stepCode, 'failed', $this->tail($stepErrorOutput !== '' ? $stepErrorOutput : $stepOutput));
                $report['failedStep'] = $stepCode;
                $report['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $this->touchProgress($job, 'failed', 'Falha na etapa: ' . $stepCode . '.', [
                    'currentStep' => $stepCode,
                    'steps' => $steps,
                    'report' => $report,
                    'outputTail' => $this->tail($output . "\n" . $stepErrorOutput),
                ]);
                throw new \RuntimeException(trim($stepErrorOutput) !== '' ? $this->tail($stepErrorOutput) : 'Falha no script de provisionamento.');
            }

            $steps = $this->markStep($steps, $stepCode, 'succeeded', null, round(microtime(true) - $stepStartedAt, 3));
        }

        $report['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $report['completedSteps'] = count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? '') === 'succeeded'));
        $this->touchProgress($job, 'completed', 'Provisionamento concluido. Gravando resumo.', [
            'steps' => $steps,
            'report' => $report,
            'outputTail' => $this->tail($output),
        ]);

        return [
            'phase' => 'completed',
            'message' => 'Provisionamento concluido.',
            'subscriberCode' => (string) ($payload['subscriberCode'] ?? ''),
            'databaseIdentity' => (string) ($payload['databaseIdentity'] ?? ''),
            'databaseName' => (string) ($payload['databaseName'] ?? ''),
            'script' => basename($script),
            'outputTail' => $this->tail($output),
            'steps' => $steps,
            'report' => $report,
        ];
    }

    private function resolveScript(string $projectRoot): string
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';
        $script = $isWindows
            ? $projectRoot . '/scripts/provision-saas-subscriber.ps1'
            : $projectRoot . '/scripts/provision-saas-subscriber.sh';

        if (!is_file($script)) {
            throw new \RuntimeException('Script de provisionamento SaaS nao encontrado.');
        }

        return $script;
    }

    private function buildCommand(string $script, string $backendDir, array $payload, string $onlyStep): array
    {
        $isWindows = \DIRECTORY_SEPARATOR === '\\';
        if ($isWindows) {
            return [
                'powershell',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $script,
                '-BackendDir',
                $backendDir,
                '-SubscriberCode',
                (string) ($payload['subscriberCode'] ?? ''),
                '-SubscriberName',
                (string) ($payload['subscriberName'] ?? ''),
                '-SubscriberDocument',
                (string) ($payload['subscriberDocument'] ?? ''),
                '-InstanceCode',
                (string) ($payload['instanceCode'] ?? ''),
                '-DatabaseEnvironment',
                (string) ($payload['databaseEnvironment'] ?? 'prod'),
                '-DatabaseIdentity',
                (string) ($payload['databaseIdentity'] ?? ''),
                '-DatabaseName',
                (string) ($payload['databaseName'] ?? ''),
                '-AdminUsername',
                (string) ($payload['adminUsername'] ?? 'admin'),
                '-AdminPasswordEnv',
                'CONSTRUTOR_PG_ADMIN_PASSWORD',
                '-OnlyStep',
                $onlyStep,
            ];
        }

        return [
            'bash',
            $script,
            '--backend-dir=' . $backendDir,
            '--subscriber-code=' . (string) ($payload['subscriberCode'] ?? ''),
            '--subscriber-name=' . (string) ($payload['subscriberName'] ?? ''),
            '--subscriber-document=' . (string) ($payload['subscriberDocument'] ?? ''),
            '--database-environment=' . (string) ($payload['databaseEnvironment'] ?? 'prod'),
            '--database-identity=' . (string) ($payload['databaseIdentity'] ?? ''),
            '--database-name=' . (string) ($payload['databaseName'] ?? ''),
            '--admin-username=' . (string) ($payload['adminUsername'] ?? 'admin'),
            '--admin-password-env=CONSTRUTOR_PG_ADMIN_PASSWORD',
            '--only-step=' . $onlyStep,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function buildProcessEnvironment(array $payload): array
    {
        $env = [];
        if ((string) ($payload['adminPassword'] ?? '') !== '') {
            $env['CONSTRUTOR_PG_ADMIN_PASSWORD'] = (string) $payload['adminPassword'];
        }

        return $env;
    }

    private function touchProgress(RuntimeAsyncJob $job, string $phase, string $message, array $extra = []): void
    {
        $job->setResult(array_merge([
            'phase' => $phase,
            'message' => $message,
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], $extra));
        $this->entityManager->flush();
    }

    private function tail(string $text, int $length = 4000): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $length ? mb_substr($text, -1 * $length) : $text;
    }

    private function initialSteps(string $retryFromStep): array
    {
        $steps = [];
        $retryEnabled = $retryFromStep !== '' && in_array($retryFromStep, self::STEP_ORDER, true);
        $retryIndex = $retryEnabled ? array_search($retryFromStep, self::STEP_ORDER, true) : false;
        foreach (self::STEP_ORDER as $index => $stepCode) {
            $steps[] = [
                'code' => $stepCode,
                'status' => $retryEnabled && $index < $retryIndex ? 'reused' : 'pending',
            ];
        }

        return $steps;
    }

    private function markStep(array $steps, string $stepCode, string $status, ?string $message = null, ?float $durationSeconds = null): array
    {
        foreach ($steps as $index => $step) {
            if (($step['code'] ?? '') !== $stepCode) {
                continue;
            }
            $steps[$index]['status'] = $status;
            if ($message !== null) {
                $steps[$index]['message'] = $message;
            }
            if ($durationSeconds !== null) {
                $steps[$index]['durationSeconds'] = $durationSeconds;
            }
        }

        return $steps;
    }

    private function readStepStatus(array $steps, string $stepCode): string
    {
        foreach ($steps as $step) {
            if (($step['code'] ?? '') === $stepCode) {
                return (string) ($step['status'] ?? 'pending');
            }
        }

        return 'pending';
    }
}
