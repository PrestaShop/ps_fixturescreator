<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Statistics;

use Doctrine\DBAL\Connection;

/**
 * Computes the figures displayed on the dashboard: how many entities exist per type,
 * and how big each table is. Everything is read straight from the database so it stays cheap
 * even on very large datasets (COUNT(*) on InnoDB is the only potentially slow part).
 */
class StatisticsProvider
{
    private Connection $connection;
    private string $dbPrefix;

    public function __construct(Connection $connection, string $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * Definition of every counter shown on the dashboard.
     * 'table' is counted with COUNT(*), optionally filtered by 'where'.
     *
     * @return array<string, array<int, array{key: string, label: string, table: string, where?: string, generator?: string}>>
     */
    public function getCounterDefinitions(): array
    {
        return [
            'catalog' => [
                ['key' => 'products', 'label' => 'Products', 'table' => 'product', 'generator' => 'products'],
                ['key' => 'products_combinations', 'label' => 'Products with combinations', 'table' => 'product', 'where' => "product_type = 'combinations'", 'generator' => 'products_with_combinations'],
                ['key' => 'combinations', 'label' => 'Combinations', 'table' => 'product_attribute'],
                ['key' => 'categories', 'label' => 'Categories', 'table' => 'category', 'generator' => 'categories'],
                ['key' => 'images', 'label' => 'Product images', 'table' => 'image'],
                ['key' => 'manufacturers', 'label' => 'Brands', 'table' => 'manufacturer', 'generator' => 'manufacturers'],
                ['key' => 'suppliers', 'label' => 'Suppliers', 'table' => 'supplier', 'generator' => 'suppliers'],
                ['key' => 'attribute_groups', 'label' => 'Attribute groups', 'table' => 'attribute_group', 'generator' => 'attributes'],
                ['key' => 'attributes', 'label' => 'Attribute values', 'table' => 'attribute'],
                ['key' => 'features', 'label' => 'Features', 'table' => 'feature', 'generator' => 'features'],
                ['key' => 'feature_values', 'label' => 'Feature values', 'table' => 'feature_value'],
                ['key' => 'feature_products', 'label' => 'Feature ↔ product links', 'table' => 'feature_product'],
                ['key' => 'specific_prices', 'label' => 'Specific prices', 'table' => 'specific_price', 'generator' => 'specific_prices'],
                ['key' => 'tags', 'label' => 'Tags', 'table' => 'tag', 'generator' => 'tags'],
                ['key' => 'product_tags', 'label' => 'Tag ↔ product links', 'table' => 'product_tag'],
                ['key' => 'stock_movements', 'label' => 'Stock movements', 'table' => 'stock_mvt'],
                ['key' => 'search_words', 'label' => 'Search index words', 'table' => 'search_word'],
                ['key' => 'search_index', 'label' => 'Search index entries', 'table' => 'search_index'],
            ],
            'customers' => [
                ['key' => 'customers', 'label' => 'Customers', 'table' => 'customer', 'generator' => 'customers'],
                ['key' => 'addresses', 'label' => 'Addresses', 'table' => 'address', 'where' => 'id_customer > 0 AND deleted = 0'],
                ['key' => 'groups', 'label' => 'Customer groups', 'table' => 'group', 'generator' => 'customer_groups'],
                ['key' => 'guests', 'label' => 'Guests', 'table' => 'guest', 'generator' => 'traffic'],
            ],
            'sales' => [
                ['key' => 'carts', 'label' => 'Carts', 'table' => 'cart', 'generator' => 'carts'],
                ['key' => 'cart_products', 'label' => 'Cart lines', 'table' => 'cart_product'],
                ['key' => 'orders', 'label' => 'Orders', 'table' => 'orders', 'generator' => 'orders'],
                ['key' => 'order_details', 'label' => 'Order lines', 'table' => 'order_detail'],
                ['key' => 'order_histories', 'label' => 'Order status changes', 'table' => 'order_history'],
                ['key' => 'order_invoices', 'label' => 'Invoices', 'table' => 'order_invoice'],
                ['key' => 'order_payments', 'label' => 'Payments', 'table' => 'order_payment'],
                ['key' => 'cart_rules', 'label' => 'Cart rules', 'table' => 'cart_rule', 'generator' => 'cart_rules'],
                ['key' => 'carriers', 'label' => 'Carriers', 'table' => 'carrier', 'where' => 'deleted = 0', 'generator' => 'carriers'],
            ],
            'support' => [
                ['key' => 'customer_threads', 'label' => 'Customer threads', 'table' => 'customer_thread', 'generator' => 'customer_threads'],
                ['key' => 'customer_messages', 'label' => 'Customer messages', 'table' => 'customer_message'],
            ],
            'content' => [
                ['key' => 'cms_pages', 'label' => 'CMS pages', 'table' => 'cms', 'generator' => 'cms_pages'],
                ['key' => 'cms_categories', 'label' => 'CMS categories', 'table' => 'cms_category'],
                ['key' => 'stores', 'label' => 'Stores', 'table' => 'store', 'generator' => 'stores'],
                ['key' => 'employees', 'label' => 'Employees', 'table' => 'employee', 'generator' => 'employees'],
            ],
            'traffic' => [
                ['key' => 'connections', 'label' => 'Connections', 'table' => 'connections', 'generator' => 'traffic'],
                ['key' => 'connections_pages', 'label' => 'Pages viewed (connections)', 'table' => 'connections_page'],
                ['key' => 'connections_sources', 'label' => 'Connection sources', 'table' => 'connections_source'],
            ],
            'system' => [
                ['key' => 'shops', 'label' => 'Shops', 'table' => 'shop'],
                ['key' => 'languages', 'label' => 'Languages', 'table' => 'lang'],
                ['key' => 'currencies', 'label' => 'Currencies', 'table' => 'currency'],
                ['key' => 'hooks_modules', 'label' => 'Hook registrations', 'table' => 'hook_module'],
                ['key' => 'configuration', 'label' => 'Configuration entries', 'table' => 'configuration'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{key: string, label: string, count: int, generator: string|null}>>
     */
    public function getCounters(): array
    {
        $counters = [];
        foreach ($this->getCounterDefinitions() as $group => $definitions) {
            foreach ($definitions as $definition) {
                $counters[$group][] = [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'count' => $this->count($definition['table'], $definition['where'] ?? null),
                    'generator' => $definition['generator'] ?? null,
                ];
            }
        }

        return $counters;
    }

    /**
     * @return array<string, int> flat key => count map
     */
    public function getFlatCounters(): array
    {
        $flat = [];
        foreach ($this->getCounters() as $counters) {
            foreach ($counters as $counter) {
                $flat[$counter['key']] = $counter['count'];
            }
        }

        return $flat;
    }

    public function count(string $table, ?string $where = null): int
    {
        try {
            $sql = sprintf('SELECT COUNT(*) FROM `%s%s`', $this->dbPrefix, $table);
            if ($where) {
                $sql .= ' WHERE ' . $where;
            }

            return (int) $this->connection->executeQuery($sql)->fetchOne();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Biggest tables of the shop database (data + indexes), from information_schema.
     *
     * @return array<int, array{table: string, rows: int, data_bytes: int, index_bytes: int, total_bytes: int}>
     */
    public function getTableSizes(int $limit = 20): array
    {
        try {
            $rows = $this->connection->executeQuery(
                'SELECT TABLE_NAME AS table_name, TABLE_ROWS AS table_rows, DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :prefix
                 ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                 LIMIT ' . (int) $limit,
                ['prefix' => str_replace('_', '\\_', $this->dbPrefix) . '%']
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'table' => substr((string) $row['table_name'], strlen($this->dbPrefix)),
                'rows' => (int) $row['table_rows'],
                'data_bytes' => (int) $row['data_length'],
                'index_bytes' => (int) $row['index_length'],
                'total_bytes' => (int) $row['data_length'] + (int) $row['index_length'],
            ];
        }, $rows);
    }

    /**
     * @return array{tables: int, rows: int, data_bytes: int, index_bytes: int, total_bytes: int}
     */
    public function getDatabaseSize(): array
    {
        try {
            $row = $this->connection->executeQuery(
                'SELECT COUNT(*) AS tables_count, SUM(TABLE_ROWS) AS rows_count, SUM(DATA_LENGTH) AS data_length, SUM(INDEX_LENGTH) AS index_length
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :prefix',
                ['prefix' => str_replace('_', '\\_', $this->dbPrefix) . '%']
            )->fetchAssociative();
        } catch (\Throwable $e) {
            $row = false;
        }

        if (!$row) {
            return ['tables' => 0, 'rows' => 0, 'data_bytes' => 0, 'index_bytes' => 0, 'total_bytes' => 0];
        }

        return [
            'tables' => (int) $row['tables_count'],
            'rows' => (int) $row['rows_count'],
            'data_bytes' => (int) $row['data_length'],
            'index_bytes' => (int) $row['index_length'],
            'total_bytes' => (int) $row['data_length'] + (int) $row['index_length'],
        ];
    }

    /**
     * Orders per month over the last year, a quick sanity check that generated sales are spread over time.
     *
     * @return array<int, array{month: string, orders: int, revenue: float}>
     */
    public function getOrdersPerMonth(int $months = 12): array
    {
        try {
            $rows = $this->connection->executeQuery(
                sprintf(
                    'SELECT DATE_FORMAT(date_add, \'%%Y-%%m\') AS month, COUNT(*) AS orders, SUM(total_paid_tax_incl / conversion_rate) AS revenue
                     FROM `%sorders`
                     WHERE date_add >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                     GROUP BY month ORDER BY month ASC',
                    $this->dbPrefix,
                    $months
                )
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'month' => (string) $row['month'],
                'orders' => (int) $row['orders'],
                'revenue' => round((float) $row['revenue'], 2),
            ];
        }, $rows);
    }

    /**
     * Distribution of products per category depth, useful to check the generated tree shape.
     *
     * @return array<int, array{depth: int, categories: int}>
     */
    public function getCategoryDepthDistribution(): array
    {
        try {
            $rows = $this->connection->executeQuery(
                sprintf('SELECT level_depth AS depth, COUNT(*) AS categories FROM `%scategory` GROUP BY level_depth ORDER BY level_depth', $this->dbPrefix)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            return ['depth' => (int) $row['depth'], 'categories' => (int) $row['categories']];
        }, $rows);
    }
}
