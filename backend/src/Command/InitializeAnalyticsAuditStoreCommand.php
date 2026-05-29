<?php

namespace App\Command;

use App\Runtime\RuntimeAnalyticsAuditStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analytics:audit:init', description: 'Inicializa a estrutura de auditoria analytics no banco separado.')]
class InitializeAnalyticsAuditStoreCommand extends Command
{
    public function __construct(
        private readonly RuntimeAnalyticsAuditStore $auditStore,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->auditStore->isEnabled()) {
            $io->warning('Auditoria analytics desabilitada. Configure ANALYTICS_AUDIT_ENABLED=1 e ANALYTICS_AUDIT_DATABASE_URL.');
            return Command::SUCCESS;
        }

        $this->auditStore->initializeSchema();
        $io->success('Estrutura de auditoria analytics pronta no banco separado.');

        return Command::SUCCESS;
    }
}
