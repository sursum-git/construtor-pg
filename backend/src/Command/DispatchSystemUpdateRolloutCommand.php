<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update:dispatch-rollout', description: 'Despacha o rollout SaaS de uma release para o orquestrador externo.')]
class DispatchSystemUpdateRolloutCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::REQUIRED, 'Versao da release.')
            ->addOption('subscriber-code', null, InputOption::VALUE_REQUIRED, 'Assinante alvo no sistema central.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->updates->dispatchRollout(
            trim((string) $input->getArgument('version')),
            trim((string) ($input->getOption('subscriber-code') ?? '')) ?: null
        );

        $dispatch = is_array($result['dispatch'] ?? null) ? $result['dispatch'] : [];
        $output->writeln('Release: ' . (string) ($result['releaseVersion'] ?? '-'));
        $output->writeln('Status: ' . (string) ($dispatch['status'] ?? '-'));
        $output->writeln('Endpoint: ' . (string) (($dispatch['endpoint'] ?? '-') ?: '-'));

        return in_array((string) ($dispatch['status'] ?? ''), ['dispatched', 'disabled'], true) ? Command::SUCCESS : Command::FAILURE;
    }
}
