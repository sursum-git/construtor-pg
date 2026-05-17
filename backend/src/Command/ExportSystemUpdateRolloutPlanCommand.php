<?php

namespace App\Command;

use App\Runtime\SystemUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update:rollout-plan', description: 'Exporta o plano de rollout de uma release para uso externo.')]
class ExportSystemUpdateRolloutPlanCommand extends Command
{
    public function __construct(
        private readonly SystemUpdateService $updates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('version', InputArgument::REQUIRED, 'Versao da release.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plan = $this->updates->buildRolloutPlan(trim((string) $input->getArgument('version')));
        $output->writeln(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return Command::SUCCESS;
    }
}
