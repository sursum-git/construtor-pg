<?php

namespace App\Command;

use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\StructuralIntegrityService;
use App\Runtime\RuntimeHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:integrity:resign', description: 'Reassina registros estruturais protegidos.')]
class ResignStructuralIntegrityCommand extends Command
{
    public function __construct(
        private readonly StructuralIntegrityService $integrity,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('table', InputArgument::OPTIONAL, 'Tabela estrutural a reassinar')
            ->addArgument('recordId', InputArgument::OPTIONAL, 'ID do registro a reassinar')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Reassinar todos os registros estruturais suportados')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Motivo da reassinatura');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all = $input->getOption('all') === true;
        $table = trim((string) ($input->getArgument('table') ?? ''));
        $recordId = (int) ($input->getArgument('recordId') ?? 0);
        $reason = trim((string) ($input->getOption('reason') ?? ''));

        if ($all) {
            $this->integrity->backfillAll();
            $this->entityManager->flush();
            $io->success('Todos os registros estruturais suportados foram reassinados.');
            return Command::SUCCESS;
        }

        if ($table === '' || $recordId <= 0) {
            $io->error('Informe `table` e `recordId`, ou use --all.');
            return Command::INVALID;
        }
        if ($reason === '') {
            $io->error('Informe `--reason` para registrar o motivo da reassinatura.');
            return Command::INVALID;
        }

        try {
            $this->assertMaintenanceEnvironmentAllowed();
            $this->integrity->resignTarget($table, $recordId, [
                'source' => 'command',
                'reason' => $reason,
            ]);
            $this->entityManager->flush();
            $io->success(sprintf('Registro %s#%d reassinado com sucesso.', $table, $recordId));
            return Command::SUCCESS;
        } catch (RuntimeHttpException $error) {
            $io->error($error->getMessage());
            return Command::FAILURE;
        }
    }

    private function assertMaintenanceEnvironmentAllowed(): void
    {
        $rawAllowed = (string) ($_ENV['PROGRAM_BUILDER_MAINTENANCE_DATABASE_ENVIRONMENTS'] ?? $_SERVER['PROGRAM_BUILDER_MAINTENANCE_DATABASE_ENVIRONMENTS'] ?? 'dev,test,homolog');
        $allowed = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $rawAllowed)
        )));
        if (!$allowed) {
            $allowed = ['dev', 'test', 'homolog'];
        }

        $environment = strtolower(trim((string) ($this->environmentIdentity->resolve()['databaseEnvironment'] ?? '')));
        if ($environment !== '' && !in_array($environment, $allowed, true)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_MAINTENANCE_ENVIRONMENT_BLOCKED', 'A operacao de manutencao nao esta autorizada para o ambiente atual.', 422, [
                'operation' => 'reassinatura estrutural',
                'databaseEnvironment' => $environment,
                'allowedDatabaseEnvironments' => $allowed,
            ]);
        }
    }
}
