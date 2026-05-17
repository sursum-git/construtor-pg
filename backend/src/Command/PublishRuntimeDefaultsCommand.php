<?php

namespace App\Command;

use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:runtime:publish-defaults', description: 'Garante que o catalogo padrao essencial esteja publicado no runtime atual.')]
class PublishRuntimeDefaultsCommand extends Command
{
    private const REQUIRED_PROGRAMS = [
        'home',
        'cadastros.clientes',
        'runtime-jobs',
        'admin-integracoes',
        'processamento-clientes',
    ];

    private const REQUIRED_SCREENS = [
        'home',
        'cadastros.clientes',
        'admin.jobs',
        'admin.integracoes',
        'processamento.relatorio-clientes',
    ];

    private const REQUIRED_ENDPOINTS = [
        'home' => ['home.notifications.list', 'home.subscriber.change'],
        'cadastros.clientes' => ['read', 'create', 'update', 'delete'],
        'admin.jobs' => ['read'],
    ];

    public function __construct(
        private readonly ProgramRepository $programs,
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEndpointRepository $endpoints,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Executa app:seed-runtime-metadata antes da validacao.')
            ->addOption('fail-on-missing', null, InputOption::VALUE_NONE, 'Retorna erro se faltar qualquer item essencial.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('refresh') === true) {
            $result = $this->runNestedCommand('app:seed-runtime-metadata', [
                '--no-interaction' => true,
            ], $output);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        $rows = [];
        $missing = 0;

        foreach (self::REQUIRED_PROGRAMS as $code) {
            $exists = $this->programs->findOneBy(['code' => $code]) !== null;
            $rows[] = ['Programa', $code, $exists ? 'ok' : 'ausente'];
            if (!$exists) {
                ++$missing;
            }
        }

        foreach (self::REQUIRED_SCREENS as $screenId) {
            $exists = $this->screens->findOneBy(['screenId' => $screenId]) !== null;
            $rows[] = ['Tela', $screenId, $exists ? 'ok' : 'ausente'];
            if (!$exists) {
                ++$missing;
            }
        }

        foreach (self::REQUIRED_ENDPOINTS as $screenId => $endpointIds) {
            foreach ($endpointIds as $endpointId) {
                $exists = $this->endpoints->findOneBy([
                    'screenId' => $screenId,
                    'endpointId' => $endpointId,
                ]) !== null;
                $rows[] = ['Endpoint', $screenId . ':' . $endpointId, $exists ? 'ok' : 'ausente'];
                if (!$exists) {
                    ++$missing;
                }
            }
        }

        $io->table(['Tipo', 'Identificador', 'Status'], $rows);

        if ($missing > 0) {
            $io->warning(sprintf('Catalogo padrao com %d item(ns) ausente(s).', $missing));
            if ($input->getOption('fail-on-missing') === true) {
                return Command::FAILURE;
            }
        } else {
            $io->success('Catalogo padrao validado no runtime atual.');
        }

        return Command::SUCCESS;
    }

    private function runNestedCommand(string $name, array $arguments, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('Aplicacao Symfony indisponivel para publicar o catalogo padrao.');
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
