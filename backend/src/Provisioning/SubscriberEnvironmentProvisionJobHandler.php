<?php

namespace App\Provisioning;

use App\Entity\RuntimeAsyncJob;
use App\Runtime\RuntimeJobHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class SubscriberEnvironmentProvisionJobHandler implements RuntimeJobHandlerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
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

        $this->touchProgress($job, 'preparing', 'Preparando provisionamento do assinante.');

        $command = $this->buildCommand($script, $backendDir, $payload);
        $output = '';
        $errorOutput = '';

        $process = new Process($command, $projectRoot, null, null, 7200);
        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput, $job): void {
            if ($type === Process::ERR) {
                $errorOutput .= $buffer;
            } else {
                $output .= $buffer;
            }

            $this->touchProgress($job, 'running', 'Provisionamento em execucao.', [
                'outputTail' => $this->tail($output . "\n" . $errorOutput),
            ]);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($errorOutput) !== '' ? $this->tail($errorOutput) : 'Falha no script de provisionamento.');
        }

        $this->touchProgress($job, 'finalizing', 'Provisionamento concluido. Gravando resumo.');

        return [
            'phase' => 'completed',
            'message' => 'Provisionamento concluido.',
            'subscriberCode' => (string) ($payload['subscriberCode'] ?? ''),
            'databaseIdentity' => (string) ($payload['databaseIdentity'] ?? ''),
            'databaseName' => (string) ($payload['databaseName'] ?? ''),
            'script' => basename($script),
            'outputTail' => $this->tail($output),
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

    private function buildCommand(string $script, string $backendDir, array $payload): array
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
                '-DatabaseEnvironment',
                (string) ($payload['databaseEnvironment'] ?? 'prod'),
                '-AdminUsername',
                (string) ($payload['adminUsername'] ?? 'admin'),
                '-AdminPassword',
                (string) ($payload['adminPassword'] ?? 'admin123'),
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
            '--admin-password=' . (string) ($payload['adminPassword'] ?? 'admin123'),
        ];
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
}
