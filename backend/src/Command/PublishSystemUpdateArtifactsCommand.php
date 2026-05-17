<?php

namespace App\Command;

use App\Runtime\SystemUpdatePublicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:update:publish-artifacts', description: 'Publica manifesto e pacotes assinados para distribuicao oficial das releases.')]
class PublishSystemUpdateArtifactsCommand extends Command
{
    public function __construct(
        private readonly SystemUpdatePublicationService $publication,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::OPTIONAL, 'Versao especifica da release a publicar.')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Manifesto de origem alternativo.')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Diretorio final dos artefatos publicados.')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Base publica para reescrever packageUrl no manifesto publicado.')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Canal publicado no manifesto final.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->publication->publish(
            $input->getArgument('version') ? (string) $input->getArgument('version') : null,
            $input->getOption('source') ? (string) $input->getOption('source') : null,
            $input->getOption('output-dir') ? (string) $input->getOption('output-dir') : null,
            $input->getOption('base-url') ? (string) $input->getOption('base-url') : null,
            $input->getOption('channel') ? (string) $input->getOption('channel') : null
        );

        $io->success('Artefatos oficiais publicados.');
        $io->definitionList(
            ['Diretorio' => (string) ($result['distributionDirectory'] ?? '-')],
            ['Manifesto' => (string) ($result['manifestPath'] ?? '-')],
            ['Canal' => (string) ($result['channel'] ?? '-')],
            ['Releases' => implode(', ', (array) ($result['versions'] ?? []))]
        );

        return Command::SUCCESS;
    }
}
