<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:param:copy', description: 'Copia o banco param para a base de testes usando pg_dump/pg_restore.')]
class CopyParamDatabaseCommand extends Command
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('schema-only', null, InputOption::VALUE_NONE, 'Copia apenas o schema.')
            ->addOption('target-url', null, InputOption::VALUE_REQUIRED, 'Sobrescreve APP_TEST_DATABASE_URL para esta execução.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceUrl = $this->normalizePostgresToolUrl($this->readEnv('PARAM_DATABASE_URL'));
        $targetUrl = $this->normalizePostgresToolUrl((string) ($input->getOption('target-url') ?: $this->readEnv('APP_TEST_DATABASE_URL')));

        if ($sourceUrl === '' || $targetUrl === '') {
            $io->error('Configure PARAM_DATABASE_URL e APP_TEST_DATABASE_URL antes de copiar.');
            return Command::FAILURE;
        }

        $dumpPath = $this->kernel->getProjectDir() . '/var/param-copy.dump';
        if (!is_dir(dirname($dumpPath))) {
            mkdir(dirname($dumpPath), 0777, true);
        }

        $dump = ['pg_dump', '--format=custom', '--no-owner', '--no-acl', '--dbname=' . $sourceUrl, '--file=' . $dumpPath];
        if ($input->getOption('schema-only')) {
            $dump[] = '--schema-only';
        }

        $restore = ['pg_restore', '--clean', '--if-exists', '--no-owner', '--no-acl', '--dbname=' . $targetUrl, $dumpPath];

        $io->section('Gerando dump do banco param');
        $this->runProcess($dump, $io);

        $io->section('Restaurando dump na base de testes');
        $this->runProcess($restore, $io);

        $io->success('Banco param copiado para a base de testes.');
        return Command::SUCCESS;
    }

    private function readEnv(string $name): string
    {
        return (string) ($_ENV[$name] ?? $_SERVER[$name] ?? getenv($name) ?: '');
    }

    private function normalizePostgresToolUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || !str_starts_with($parts['scheme'], 'postgres')) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            unset($query['serverVersion'], $query['charset']);
        }

        $auth = '';
        if (isset($parts['user'])) {
            $auth = rawurlencode(rawurldecode($parts['user']));
            if (isset($parts['pass'])) {
                $auth .= ':' . rawurlencode(rawurldecode($parts['pass']));
            }
            $auth .= '@';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryString = $query ? '?' . http_build_query($query) : '';

        return $parts['scheme'] . '://' . $auth . ($parts['host'] ?? '') . $port . $path . $queryString;
    }

    private function runProcess(array $command, SymfonyStyle $io): void
    {
        $process = new Process($command, $this->kernel->getProjectDir(), null, null, 3600);
        $process->run(function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput() ?: 'Falha ao executar comando PostgreSQL.');
        }
    }
}
