<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Command;

use PrestaShop\Module\PsFixturesCreator\Generator\GeneratorRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListGeneratorsCommand extends Command
{
    private GeneratorRegistry $registry;

    public function __construct(GeneratorRegistry $registry)
    {
        parent::__construct(null);
        $this->registry = $registry;
    }

    protected function configure(): void
    {
        $this
            ->setName('prestashop:fixtures:list')
            ->setDescription('List the available fixture generators and their options')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->registry->grouped() as $group => $generators) {
            $io->section(ucfirst($group));
            foreach ($generators as $generator) {
                $io->writeln(sprintf('  <info>%s</info>  %s', str_pad($generator->getName(), 28), $generator->getLabel()));
                $io->writeln(sprintf('  %s<comment>%s</comment>', str_repeat(' ', 30), $generator->getDescription()));
                foreach ($generator->getOptions() as $option) {
                    $default = $option->getDefault();
                    $io->writeln(sprintf(
                        '  %s--option %s=%s%s',
                        str_repeat(' ', 30),
                        $option->getName(),
                        is_bool($default) ? ($default ? 'true' : 'false') : (string) $default,
                        $option->getChoices() ? ' (' . implode('|', $option->getChoices()) . ')' : ''
                    ));
                }
                $io->newLine();
            }
        }


        return 0;
    }
}
