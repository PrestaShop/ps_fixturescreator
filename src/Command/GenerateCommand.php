<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Command;

use PrestaShop\Module\PsFixturesCreator\Generator\GenerationContext;
use PrestaShop\Module\PsFixturesCreator\Generator\GenerationRunner;
use PrestaShop\Module\PsFixturesCreator\Generator\GeneratorRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generic entry point: php bin/console prestashop:fixtures:generate products --count=1000 --option images=2
 */
class GenerateCommand extends Command
{
    private GeneratorRegistry $registry;
    private GenerationRunner $runner;

    public function __construct(GeneratorRegistry $registry, GenerationRunner $runner)
    {
        parent::__construct(null);
        $this->registry = $registry;
        $this->runner = $runner;
    }

    protected function configure(): void
    {
        $this
            ->setName('prestashop:fixtures:generate')
            ->setDescription('Generate fixtures of a given type (run prestashop:fixtures:list to see the available generators and their options)')
            ->addArgument('generator', InputArgument::REQUIRED, 'Generator name (e.g. products, customers, orders...)')
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Number of entities to create', 10)
            ->addOption('option', 'o', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Generator option, as key=value (repeatable)', [])
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Split the generation in batches of this size (progress feedback, lower memory usage)', 0)
            ->addOption('shopId', null, InputOption::VALUE_REQUIRED, 'The shop identifier', 1)
            ->addOption('shopGroupId', null, InputOption::VALUE_REQUIRED, 'The shop group identifier', 1)
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'The language identifier', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $generatorName = (string) $input->getArgument('generator');

        if (!$this->registry->has($generatorName)) {
            $io->error(sprintf('Unknown generator "%s". Available: %s', $generatorName, implode(', ', array_keys($this->registry->all()))));

            return 1;
        }

        $options = [];
        foreach ((array) $input->getOption('option') as $rawOption) {
            if (false === strpos((string) $rawOption, '=')) {
                $io->error(sprintf('Invalid option "%s", expected key=value', $rawOption));

                return 1;
            }
            [$key, $value] = explode('=', (string) $rawOption, 2);
            $options[trim($key)] = trim($value);
        }

        $count = max(0, (int) $input->getOption('count'));
        $batch = max(0, (int) $input->getOption('batch'));
        $context = new GenerationContext(
            (int) $input->getOption('shopId'),
            (int) $input->getOption('shopGroupId'),
            (int) $input->getOption('languageId')
        );

        $generator = $this->registry->get($generatorName);
        $io->title(sprintf('%s: generating %d item(s)', $generator->getLabel(), $count));
        $normalized = $this->registry->normalizeOptions($generatorName, $options);
        if (!empty($normalized)) {
            $io->listing(array_map(static function ($key, $value): string {
                return sprintf('%s = %s', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }, array_keys($normalized), $normalized));
        }

        $totals = [];
        $messages = [];
        $totalDuration = 0;
        $remaining = $count;
        $batchSize = $batch > 0 ? $batch : $count;

        $progress = $io->createProgressBar($count);
        $progress->start();
        while ($remaining > 0) {
            $current = min($batchSize, $remaining);
            $run = $this->runner->run($generatorName, $current, $options, $context, 'cli');
            foreach ($run->getResult()->getCreated() as $entity => $number) {
                $totals[$entity] = ($totals[$entity] ?? 0) + $number;
            }
            $messages = array_merge($messages, $run->getResult()->getMessages());
            $totalDuration += $run->getDurationMs();
            $remaining -= $current;
            $progress->advance($current);
        }
        $progress->finish();
        $io->newLine(2);

        $rows = [];
        foreach ($totals as $entity => $number) {
            $rows[] = [$entity, number_format($number)];
        }
        if (!empty($rows)) {
            $io->table(['Entity', 'Created'], $rows);
        }
        foreach (array_unique($messages) as $message) {
            $io->warning($message);
        }
        $io->success(sprintf('Done in %s.', $this->formatDuration($totalDuration)));

        return 0;
    }

    private function formatDuration(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return $milliseconds . ' ms';
        }
        $seconds = (int) round($milliseconds / 1000);
        if ($seconds < 60) {
            return $seconds . ' s';
        }

        return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
    }
}
