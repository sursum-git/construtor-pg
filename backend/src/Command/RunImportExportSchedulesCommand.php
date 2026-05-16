<?php

namespace App\Command;

use App\ImportExport\ImportExportMappingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-export:run-schedules', description: 'Executa integracoes agendadas que estiverem vencidas.')]
class RunImportExportSchedulesCommand extends Command
{
    public function __construct(
        private readonly ImportExportMappingService $service,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->service->runDueSchedules();
        $io->success('Agendamentos processados: ' . (int) ($result['count'] ?? 0));

        foreach ((array) ($result['executed'] ?? []) as $item) {
            $io->writeln(sprintf(
                '%s -> %s',
                (string) ($item['scheduleCode'] ?? '-'),
                isset($item['error']) ? (string) $item['error'] : 'ok'
            ));
        }

        return Command::SUCCESS;
    }
}
