<?php

namespace App\Runtime;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class SystemUpdateStepRunner
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return array{step: string, status: string, output: string}
     */
    public function run(string $step): array
    {
        $commands = $this->resolveCommand($step);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $statusCode = $application->run(new ArrayInput($commands), $output);

        return [
            'step' => $step,
            'status' => $statusCode === 0 ? 'ok' : 'failed',
            'output' => trim($output->fetch()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCommand(string $step): array
    {
        return match ($step) {
            'migrate' => ['command' => 'doctrine:migrations:migrate', '--no-interaction' => true],
            'seed_runtime_metadata' => ['command' => 'app:seed-runtime-metadata', '--no-interaction' => true],
            'publish_runtime_defaults' => ['command' => 'app:runtime:publish-defaults', '--refresh' => true, '--fail-on-missing' => true],
            'integrity_monitor' => ['command' => 'app:integrity:monitor', '--fail-on-invalid' => true],
            'governance_monitor' => ['command' => 'app:governance:monitor', '--fail-on-alert' => true],
            default => throw new \RuntimeException('Passo de atualizacao nao suportado: ' . $step),
        };
    }
}
