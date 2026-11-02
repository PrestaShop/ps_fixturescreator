<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;

/**
 * Picks random existing entities (products, customers, categories...) so generated data can
 * reference real rows. Id lists are cached for the lifetime of the request/command.
 */
class EntityPicker
{
    private Connection $connection;
    private string $dbPrefix;

    /** @var array<string, int[]> */
    private array $cache = [];

    public function __construct(Connection $connection, string $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * @return int[]
     */
    public function getIds(string $table, string $column, ?string $where = null, bool $useCache = true): array
    {
        $cacheKey = $table . '|' . $column . '|' . (string) $where;
        if ($useCache && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = sprintf('SELECT `%s` FROM `%s%s`', $column, $this->dbPrefix, $table);
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        $ids = array_map('intval', $this->connection->executeQuery($sql)->fetchFirstColumn());

        if ($useCache) {
            $this->cache[$cacheKey] = $ids;
        }

        return $ids;
    }

    public function invalidate(?string $table = null): void
    {
        if (null === $table) {
            $this->cache = [];

            return;
        }
        foreach (array_keys($this->cache) as $key) {
            if (0 === strpos($key, $table . '|')) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * @param int[] $ids
     *
     * @return int[]
     */
    public function pickRandom(array $ids, int $number): array
    {
        if (empty($ids) || $number <= 0) {
            return [];
        }
        if ($number >= count($ids)) {
            return array_values($ids);
        }
        $keys = (array) array_rand($ids, $number);
        $picked = [];
        foreach ($keys as $key) {
            $picked[] = $ids[$key];
        }

        return $picked;
    }

    /**
     * @param int[] $ids
     */
    public function pickOne(array $ids): ?int
    {
        if (empty($ids)) {
            return null;
        }

        return $ids[array_rand($ids)];
    }

    /**
     * @return int[]
     */
    public function getCustomerIds(): array
    {
        return $this->getIds('customer', 'id_customer', 'deleted = 0 AND active = 1 AND is_guest = 0');
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(int $shopId, int $minDepth = 2): array
    {
        return $this->getIds(
            'category',
            'id_category',
            sprintf('active = 1 AND level_depth >= %d AND id_category IN (SELECT id_category FROM `%scategory_shop` WHERE id_shop = %d)', $minDepth, $this->dbPrefix, $shopId)
        );
    }

    /**
     * @return int[]
     */
    public function getManufacturerIds(): array
    {
        return $this->getIds('manufacturer', 'id_manufacturer', 'active = 1');
    }

    /**
     * @return int[]
     */
    public function getSupplierIds(): array
    {
        return $this->getIds('supplier', 'id_supplier', 'active = 1');
    }

    /**
     * @return int[]
     */
    public function getGroupIds(): array
    {
        return $this->getIds('group', 'id_group');
    }

    /**
     * @return int[]
     */
    public function getProductIds(int $shopId): array
    {
        return $this->getIds(
            'product',
            'id_product',
            sprintf('id_product IN (SELECT id_product FROM `%sproduct_shop` WHERE id_shop = %d AND active = 1)', $this->dbPrefix, $shopId)
        );
    }

    /**
     * @return int[]
     */
    public function getCombinationIds(int $productId): array
    {
        return $this->getIds('product_attribute', 'id_product_attribute', 'id_product = ' . $productId, false);
    }

    /**
     * Products that can be put in a cart: active, orderable, with their default combination if any.
     *
     * @return array<int, array{id_product: int, id_product_attribute: int, product_type: string}>
     */
    public function getOrderableProducts(int $shopId): array
    {
        $cacheKey = 'orderable|' . $shopId;
        if (isset($this->cache[$cacheKey])) {
            /* @phpstan-ignore-next-line */
            return $this->cache[$cacheKey];
        }

        $rows = $this->connection->executeQuery(sprintf(
            'SELECT p.id_product, ps.cache_default_attribute, p.product_type
             FROM `%sproduct` p
             INNER JOIN `%sproduct_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = %d
             WHERE ps.active = 1 AND ps.available_for_order = 1 AND ps.visibility IN ("both", "catalog")
               AND p.product_type IN ("standard", "combinations", "virtual", "")',
            $this->dbPrefix,
            $this->dbPrefix,
            $shopId
        ))->fetchAllAssociative();

        $products = array_map(static function (array $row): array {
            return [
                'id_product' => (int) $row['id_product'],
                'id_product_attribute' => (int) $row['cache_default_attribute'],
                'product_type' => (string) $row['product_type'],
            ];
        }, $rows);

        $this->cache[$cacheKey] = $products;

        return $products;
    }

    /**
     * @return int[]
     */
    public function getCarrierIds(): array
    {
        return $this->getIds('carrier', 'id_carrier', 'deleted = 0 AND active = 1');
    }

    /**
     * @return int[]
     */
    public function getAddressIdsForCustomer(int $customerId): array
    {
        return $this->getIds('address', 'id_address', 'deleted = 0 AND id_customer = ' . $customerId, false);
    }

    /**
     * @return int[]
     */
    public function getOrderIdsForCustomer(int $customerId): array
    {
        return $this->getIds('orders', 'id_order', 'id_customer = ' . $customerId, false);
    }

    /**
     * @return int[]
     */
    public function getCustomerThreadIds(): array
    {
        return $this->getIds('customer_thread', 'id_customer_thread', null, false);
    }

    /**
     * @return int[]
     */
    public function getCountryIds(): array
    {
        return $this->getIds('country', 'id_country', 'active = 1');
    }

    /**
     * @return int[]
     */
    public function getContactIds(): array
    {
        return $this->getIds('contact', 'id_contact');
    }

    /**
     * @return int[]
     */
    public function getEmployeeIds(): array
    {
        return $this->getIds('employee', 'id_employee', 'active = 1');
    }

    /**
     * @return int[]
     */
    public function getPageIds(): array
    {
        return $this->getIds('page', 'id_page', null, false);
    }

    /**
     * @return int[]
     */
    public function getCmsCategoryIds(): array
    {
        return $this->getIds('cms_category', 'id_cms_category', 'active = 1', false);
    }

    /**
     * @return int[]
     */
    public function getTaxRulesGroupIds(): array
    {
        return $this->getIds('tax_rules_group', 'id_tax_rules_group', 'active = 1 AND deleted = 0');
    }

    /**
     * @return int[]
     */
    public function getProfileIds(): array
    {
        return $this->getIds('profile', 'id_profile', 'id_profile <> 1');
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getDbPrefix(): string
    {
        return $this->dbPrefix;
    }
}
