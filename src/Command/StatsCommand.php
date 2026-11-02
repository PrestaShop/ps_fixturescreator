<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Command;

use PrestaShop\Module\PsFixturesCreator\Statistics\StatisticsProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatsCommand extends Command
{
    private StatisticsProvider $statisticsProvider;

    public function __construct(StatisticsProvider $statisticsProvider)
    {
        parent::__construct(null);
        $this->statisticsProvider = $statisticsProvider;
    }

    protected function configure(): void
    {
        $this
            ->setName('prestashop:fixtures:stats')
            ->setDescription('Show the current data volume of the shop (same figures as the back office dashboard)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('tables', null, InputOption::VALUE_REQUIRED, 'Number of biggest tables to show', 15)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counters = $this->statisticsProvider->getCounters();
        $tables = $this->statisticsProvider->getTableSizes((int) $input->getOption('tables'));
        $database = $this->statisticsProvider->getDatabaseSize();

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'counters' => $counters,
                'database' => $database,
                'tables' => $tables,
            ], JSON_PRETTY_PRINT));

            return 0;
        }

        $io = new SymfonyStyle($input, $output);
        foreach ($counters as $group => $groupCounters) {
            $io->section(ucfirst($group));
            $io->table(['Entity', 'Count'], array_map(static function (array $counter): array {
                return [$counter['label'], number_format($counter['count'])];
            }, $groupCounters));
        }

        $io->section(sprintf('Database: %s in %d tables (%s rows)', self::formatBytes($database['total_bytes']), $database['tables'], number_format($database['rows'])));
        $io->table(['Table', 'Rows (approx.)', 'Data', 'Indexes', 'Total'], array_map(static function (array $table): array {
            return [
                $table['table'],
                number_format($table['rows']),
                self::formatBytes($table['data_bytes']),
                self::formatBytes($table['index_bytes']),
                self::formatBytes($table['total_bytes']),
            ];
        }, $tables));

        return 0;
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $power), $units[$power]);
    }
}
