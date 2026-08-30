<?php

namespace ControleOnline\Command;

use ControleOnline\Service\Cte\CteEmissionProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cte:emit', description: 'Processa a fila cte_emission / invoice_task e emite CT-e via NFePHP')]
class CteEmitCommand extends Command
{
    public function __construct(private CteEmissionProcessor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Máximo de itens por execução', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $processed = $this->processor->processPending($limit);
        $output->writeln(sprintf('CT-e processados: %d', $processed));

        return Command::SUCCESS;
    }
}
