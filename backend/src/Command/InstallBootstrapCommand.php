<?php

namespace App\Command;

use App\Runtime\RuntimeEnvironmentIdentityResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:install:bootstrap', description: 'Executa o bootstrap inicial da aplicacao sem alterar a arquitetura atual.')]
class InstallBootstrapCommand extends Command
{
    public function __construct(
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('create-database', null, InputOption::VALUE_NONE, 'Cria o banco configurado antes das migrations.')
            ->addOption('skip-seed', null, InputOption::VALUE_NONE, 'Nao executa app:seed-runtime-metadata.')
            ->addOption('skip-publish-defaults', null, InputOption::VALUE_NONE, 'Nao valida/publica o catalogo padrao.')
            ->addOption('skip-integrity', null, InputOption::VALUE_NONE, 'Nao executa o monitor estrutural ao final.')
            ->addOption('database-environment', null, InputOption::VALUE_REQUIRED, 'Sobrescreve APP_DATABASE_ENVIRONMENT para a execucao atual.')
            ->addOption('database-identity', null, InputOption::VALUE_REQUIRED, 'Sobrescreve APP_DATABASE_IDENTITY para a execucao atual.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->applyEnvironmentOverrides($input);

        $environment = $this->environmentIdentity->resolve();
        $io->text(sprintf('Ambiente alvo: %s', (string) ($environment['databaseEnvironment'] ?? 'dev')));
        $io->text(sprintf('Base alvo: %s', (string) ($environment['databaseIdentity'] ?? 'db:dev')));

        if ($input->getOption('create-database') === true) {
            $result = $this->runNestedCommand('doctrine:database:create', [
                '--if-not-exists' => true,
            ], $output);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        $result = $this->runNestedCommand('doctrine:migrations:migrate', [
            '--no-interaction' => true,
        ], $output);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        if ($input->getOption('skip-seed') !== true) {
            $result = $this->runNestedCommand('app:seed-runtime-metadata', [
                '--no-interaction' => true,
            ], $output);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        if ($input->getOption('skip-publish-defaults') !== true) {
            $result = $this->runNestedCommand('app:runtime:publish-defaults', [
                '--fail-on-missing' => true,
            ], $output);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        if ($input->getOption('skip-integrity') !== true) {
            $result = $this->runNestedCommand('app:integrity:monitor', [
                '--fail-on-invalid' => true,
            ], $output);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        $io->success('Bootstrap inicial concluido.');
        return Command::SUCCESS;
    }

    private function applyEnvironmentOverrides(InputInterface $input): void
    {
        $databaseEnvironment = trim((string) $input->getOption('database-environment'));
        $databaseIdentity = trim((string) $input->getOption('database-identity'));

        if ($databaseEnvironment !== '') {
            $_SERVER['APP_DATABASE_ENVIRONMENT'] = $databaseEnvironment;
            $_ENV['APP_DATABASE_ENVIRONMENT'] = $databaseEnvironment;
            putenv('APP_DATABASE_ENVIRONMENT=' . $databaseEnvironment);
        }

        if ($databaseIdentity !== '') {
            $_SERVER['APP_DATABASE_IDENTITY'] = $databaseIdentity;
            $_ENV['APP_DATABASE_IDENTITY'] = $databaseIdentity;
            putenv('APP_DATABASE_IDENTITY=' . $databaseIdentity);
        }
    }

    private function runNestedCommand(string $name, array $arguments, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('Aplicacao Symfony indisponivel para orquestrar bootstrap.');
        }

        $command = $application->find($name);
        $nestedInput = new ArrayInput(array_merge(['command' => $name], $arguments));
        $nestedInput->setInteractive(false);

        $nestedOutput = $output instanceof ConsoleOutputInterface
            ? $output->section()
            : $output;

        return $command->run($nestedInput, $nestedOutput);
    }
}
