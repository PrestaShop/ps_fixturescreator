<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Statistics;

use Doctrine\DBAL\Connection;
use PrestaShop\Module\PsFixturesCreator\Generator\GenerationResult;

/**
 * Persists every generation run so the dashboard can show what was loaded, when, and how long it took.
 */
class RunLogger
{
    public const TABLE = 'fixturescreator_run';

    private Connection $connection;
    private string $dbPrefix;

    public function __construct(Connection $connection, string $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function log(string $generator, int $count, array $options, GenerationResult $result, int $durationMs, string $source, int $shopId): void
    {
        try {
            $this->connection->insert($this->dbPrefix . self::TABLE, [
                'generator' => $generator,
                'quantity' => $count,
                'options' => json_encode($options),
                'created' => json_encode($result->getCreated()),
                'duration_ms' => $durationMs,
                'source' => $source,
                'id_shop' => $shopId,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break a generation (e.g. table missing after a manual upgrade)
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentRuns(int $limit = 20): array
    {
        try {
            $rows = $this->connection->executeQuery(
                sprintf('SELECT * FROM `%s%s` ORDER BY id_run DESC LIMIT %d', $this->dbPrefix, self::TABLE, $limit)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['options'] = json_decode((string) $row['options'], true) ?: [];
            $row['created'] = json_decode((string) $row['created'], true) ?: [];
            $row['quantity'] = (int) $row['quantity'];
            $row['duration_ms'] = (int) $row['duration_ms'];
        }

        return $rows;
    }

    /**
     * @return array<string, array{runs: int, quantity: int, duration_ms: int}>
     */
    public function getTotalsByGenerator(): array
    {
        try {
            $rows = $this->connection->executeQuery(
                sprintf('SELECT generator, COUNT(*) AS runs, SUM(quantity) AS quantity, SUM(duration_ms) AS duration_ms FROM `%s%s` GROUP BY generator', $this->dbPrefix, self::TABLE)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['generator']] = [
                'runs' => (int) $row['runs'],
                'quantity' => (int) $row['quantity'],
                'duration_ms' => (int) $row['duration_ms'],
            ];
        }

        return $totals;
    }

    public static function getCreateTableSql(string $dbPrefix, string $engine): string
    {
        return sprintf(
            'CREATE TABLE IF NOT EXISTS `%s%s` (
                `id_run` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `generator` VARCHAR(64) NOT NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
                `options` TEXT,
                `created` TEXT,
                `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                `source` VARCHAR(16) NOT NULL DEFAULT \'cli\',
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_run`),
                KEY `generator` (`generator`)
            ) ENGINE=%s DEFAULT CHARSET=utf8mb4',
            $dbPrefix,
            self::TABLE,
            $engine
        );
    }

    public static function getDropTableSql(string $dbPrefix): string
    {
        return sprintf('DROP TABLE IF EXISTS `%s%s`', $dbPrefix, self::TABLE);
    }
}
