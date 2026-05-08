<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'doctrine:query:sql', description: 'Executa SQL usando a conexao DBAL configurada.')]
class QuerySqlCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sql', InputArgument::REQUIRED, 'SQL a executar.')
            ->addOption('max-rows', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de linhas exibidas.', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sql = trim((string) $input->getArgument('sql'));
        $maxRows = max(1, (int) $input->getOption('max-rows'));

        if ($sql === '') {
            $io->error('Informe uma consulta SQL.');
            return Command::FAILURE;
        }

        if (!$this->returnsRows($sql)) {
            $affectedRows = $this->connection->executeStatement($sql);
            $io->success($affectedRows . ' linha(s) afetada(s).');
            return Command::SUCCESS;
        }

        $result = $this->connection->executeQuery($sql);
        $rows = [];
        $truncated = false;

        while (($row = $result->fetchAssociative()) !== false) {
            if (count($rows) >= $maxRows) {
                $truncated = true;
                break;
            }
            $rows[] = array_map($this->stringify(...), $row);
        }

        if (!$rows) {
            $io->success('Consulta executada sem linhas.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(array_keys($rows[0]));
        $table->setRows(array_map('array_values', $rows));
        $table->render();

        $message = count($rows) . ' linha(s) exibida(s).';
        if ($truncated) {
            $message .= ' Resultado limitado por --max-rows=' . $maxRows . '.';
        }
        $io->success($message);

        return Command::SUCCESS;
    }

    private function returnsRows(string $sql): bool
    {
        return (bool) preg_match('/^\s*(select|with|show|explain|values)\b/i', $sql);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }
}
