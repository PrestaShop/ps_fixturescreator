<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Command;

use PrestaShop\Module\PsFixturesCreator\Generator\GenerationContext;
use PrestaShop\Module\PsFixturesCreator\Generator\GenerationRunner;
use PrestaShop\Module\PsFixturesCreator\Generator\GeneratorRegistry;
use PrestaShop\Module\PsFixturesCreator\Preset\PresetRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PresetCommand extends Command
{
    private PresetRegistry $presets;
    private GeneratorRegistry $registry;
    private GenerationRunner $runner;

    public function __construct(PresetRegistry $presets, GeneratorRegistry $registry, GenerationRunner $runner)
    {
        $this->presets = $presets;
        $this->registry = $registry;
        $this->runner = $runner;
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->setName('prestashop:fixtures:preset')
            ->setDescription('Load a whole dataset (small, medium, large) in one go')
            ->addArgument('preset', InputArgument::REQUIRED, 'Preset name: ' . implode(', ', array_keys($this->presets->all())))
            ->addOption('scale', null, InputOption::VALUE_REQUIRED, 'Multiply every quantity by this factor (e.g. 0.1 for a quick dry run, 2 to double)', 1)
            ->addOption('skip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Generator(s) to skip', [])
            ->addOption('shopId', null, InputOption::VALUE_REQUIRED, 'The shop identifier', 1)
            ->addOption('shopGroupId', null, InputOption::VALUE_REQUIRED, 'The shop group identifier', 1)
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'The language identifier', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $presetName = (string) $input->getArgument('preset');
        if (!$this->presets->has($presetName)) {
            $io->error(sprintf('Unknown preset "%s". Available: %s', $presetName, implode(', ', array_keys($this->presets->all()))));

            return 1;
        }

        $preset = $this->presets->get($presetName);
        $scale = max(0.0, (float) $input->getOption('scale'));
        $skip = (array) $input->getOption('skip');
        $context = new GenerationContext(
            (int) $input->getOption('shopId'),
            (int) $input->getOption('shopGroupId'),
            (int) $input->getOption('languageId')
        );

        $io->title(sprintf('Preset "%s" (scale ×%s)', $preset['label'], rtrim(rtrim(number_format($scale, 2), '0'), '.')));
        $io->text($preset['description']);

        $start = microtime(true);
        $grandTotal = [];
        foreach ($preset['steps'] as $step) {
            if (in_array($step['generator'], $skip, true) || !$this->registry->has($step['generator'])) {
                continue;
            }
            $count = (int) ceil($step['count'] * $scale);
            if ($count <= 0) {
                continue;
            }
            $generator = $this->registry->get($step['generator']);
            $io->section(sprintf('%s × %s', $generator->getLabel(), number_format($count)));

            $batchSize = max(1, $generator->getBatchSize() * 4);
            $remaining = $count;
            $progress = $io->createProgressBar($count);
            $progress->start();
            $stepTotals = [];
            while ($remaining > 0) {
                $current = min($batchSize, $remaining);
                $run = $this->runner->run($step['generator'], $current, $step['options'], $context, 'cli');
                foreach ($run->getResult()->getCreated() as $entity => $number) {
                    $stepTotals[$entity] = ($stepTotals[$entity] ?? 0) + $number;
                    $grandTotal[$entity] = ($grandTotal[$entity] ?? 0) + $number;
                }
                foreach ($run->getResult()->getMessages() as $message) {
                    $io->newLine();
                    $io->warning($message);
                }
                $remaining -= $current;
                $progress->advance($current);
            }
            $progress->finish();
            $io->newLine();
            $io->text(implode(', ', array_map(static function (string $entity, int $number): string {
                return sprintf('%s: %s', $entity, number_format($number));
            }, array_keys($stepTotals), $stepTotals)));
        }

        $rows = [];
        foreach ($grandTotal as $entity => $number) {
            $rows[] = [$entity, number_format($number)];
        }
        $io->newLine();
        $io->table(['Entity', 'Created'], $rows);
        $io->success(sprintf('Preset loaded in %d min.', (int) round((microtime(true) - $start) / 60)));

        return 0;
    }
}
