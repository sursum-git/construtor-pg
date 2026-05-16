<?php

namespace App\Command;

use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:integrity:monitor', description: 'Executa a verificacao estrutural periodica e gera um resumo operacional.')]
class MonitorStructuralIntegrityCommand extends Command
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
        $this->addOption('fail-on-invalid', null, InputOption::VALUE_NONE, 'Retorna erro quando houver registros invalidos.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->integrity->verifyAll();
        $this->entityManager->flush();

        $invalid = array_values(array_filter($results, static fn (array $item): bool => ($item['status'] ?? '') !== 'valid'));
        $environment = $this->environmentIdentity->resolve();
        $io->text(sprintf('Ambiente: %s', (string) ($environment['databaseEnvironment'] ?? '-')));
        $io->text(sprintf('Base: %s', (string) ($environment['databaseIdentity'] ?? '-')));
        $io->text(sprintf('Registros verificados: %d', count($results)));
        $io->text(sprintf('Registros invalidos: %d', count($invalid)));

        foreach ($invalid as $item) {
            $io->warning(sprintf(
                '%s#%d -> %s',
                (string) ($item['tableName'] ?? '-'),
                (int) ($item['recordId'] ?? 0),
                (string) ($item['message'] ?? 'Violacao de integridade')
            ));
        }

        if ($invalid && $input->getOption('fail-on-invalid') === true) {
            return Command::FAILURE;
        }

        $io->success($invalid ? 'Monitoramento concluido com divergencias.' : 'Monitoramento concluido sem divergencias.');
        return Command::SUCCESS;
    }
}
