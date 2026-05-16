<?php

namespace App\Command;

use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:integrity:check', description: 'Verifica a integridade estrutural dos registros assinados.')]
class CheckStructuralIntegrityCommand extends Command
{
    public function __construct(
        private readonly StructuralIntegrityService $integrity,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->integrity->verifyAll();
        $this->entityManager->flush();

        $invalid = array_values(array_filter($results, static fn (array $item): bool => ($item['status'] ?? '') !== 'valid'));
        $io->text(sprintf('Registros verificados: %d', count($results)));
        $io->text(sprintf('Registros invalidos: %d', count($invalid)));
        if ($invalid) {
            foreach ($invalid as $item) {
                $io->warning(sprintf(
                    '%s#%d -> %s',
                    (string) $item['tableName'],
                    (int) $item['recordId'],
                    (string) ($item['message'] ?? 'Violacao de integridade')
                ));
            }
            return Command::FAILURE;
        }

        $io->success('Nenhuma violacao de integridade foi encontrada.');
        return Command::SUCCESS;
    }
}
