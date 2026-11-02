<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Cache;
use Configuration;
use Doctrine\DBAL\Connection;
use Faker\Generator as Faker;
use Product;
use StockAvailable;
use Tools;

/**
 * Everything that makes a generated product look like a real one: catalog placement (categories,
 * brand, supplier), descriptive fields, stock, tags, specific prices and a creation date in the past.
 */
class ProductEnricher
{
    private Faker $faker;
    private Connection $connection;
    private string $dbPrefix;
    private EntityPicker $entityPicker;
    private TagCreator $tagCreator;
    private SpecificPriceCreator $specificPriceCreator;
    private DateSpreader $dateSpreader;

    public function __construct(
        Faker $faker,
        Connection $connection,
        string $dbPrefix,
        EntityPicker $entityPicker,
        TagCreator $tagCreator,
        SpecificPriceCreator $specificPriceCreator,
        DateSpreader $dateSpreader
    ) {
        $this->faker = $faker;
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
        $this->entityPicker = $entityPicker;
        $this->tagCreator = $tagCreator;
        $this->specificPriceCreator = $specificPriceCreator;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * @return array<string, mixed> default enrichment options
     */
    public static function getDefaultOptions(): array
    {
        return [
            'categories_per_product' => 2,
            'assign_manufacturer' => true,
            'assign_supplier' => true,
            'tags' => 3,
            'specific_prices' => 0,
            'quantity' => 1000,
            'spread_months' => 12,
        ];
    }

    /**
     * Fills the descriptive/pricing fields of a product object (before add() or update()).
     *
     * @param array<int, string> $names id_lang => name
     */
    public function fillBaseFields(Product $product, array $names, int $shopId): void
    {
        $descriptions = [];
        $shortDescriptions = [];
        $linkRewrites = [];
        $metaTitles = [];
        $metaDescriptions = [];
        foreach ($names as $langId => $name) {
            $linkRewrites[$langId] = Tools::str2url($name . '-' . uniqid());
            $descriptions[$langId] = '<p>' . implode('</p><p>', $this->faker->paragraphs($this->faker->numberBetween(1, 4))) . '</p>';
            $shortDescriptions[$langId] = '<p>' . $this->faker->sentence($this->faker->numberBetween(8, 20)) . '</p>';
            $metaTitles[$langId] = substr($name, 0, 128);
            $metaDescriptions[$langId] = substr($this->faker->sentence(12), 0, 255);
        }

        $product->name = $names;
        $product->link_rewrite = $linkRewrites;
        $product->description = $descriptions;
        $product->description_short = $shortDescriptions;
        $product->meta_title = $metaTitles;
        $product->meta_description = $metaDescriptions;

        $product->price = $this->faker->randomFloat(2, 1, 1000);
        $product->wholesale_price = round($product->price * $this->faker->randomFloat(2, 0.3, 0.8), 2);
        $product->reference = strtoupper($this->faker->bothify('FAKE-????-#####'));
        $product->ean13 = $this->faker->ean13;
        $product->weight = $this->faker->randomFloat(3, 0.05, 25);
        $product->active = true;
        $product->available_for_order = true;
        $product->show_price = true;
        $product->on_sale = $this->faker->boolean(10);
        $product->online_only = $this->faker->boolean(5);
        $product->condition = 'new';
        $product->visibility = 'both';
        $product->minimal_quantity = 1;
        $product->indexed = false;
        $product->id_shop_default = $shopId;

        $taxRulesGroupId = $this->entityPicker->pickOne($this->entityPicker->getTaxRulesGroupIds());
        if (null !== $taxRulesGroupId) {
            $product->id_tax_rules_group = $taxRulesGroupId;
        }

        $categoryIds = $this->entityPicker->getCategoryIds($shopId);
        $defaultCategoryId = $this->entityPicker->pickOne($categoryIds) ?? (int) Configuration::get('PS_HOME_CATEGORY');
        $product->id_category_default = $defaultCategoryId;

        $manufacturerId = $this->entityPicker->pickOne($this->entityPicker->getManufacturerIds());
        if (null !== $manufacturerId) {
            $product->id_manufacturer = $manufacturerId;
        }
        $supplierId = $this->entityPicker->pickOne($this->entityPicker->getSupplierIds());
        if (null !== $supplierId) {
            $product->id_supplier = $supplierId;
        }
    }

    /**
     * Post-creation enrichment. Works for simple products ($combinationIds empty) and products with combinations.
     *
     * @param int[] $combinationIds
     * @param array<string, mixed> $options see getDefaultOptions()
     */
    public function enrich(int $productId, array $combinationIds, array $options, int $shopId, bool $stockAlreadySet = false): void
    {
        $options = array_merge(self::getDefaultOptions(), $options);
        $product = new Product($productId, false, null, $shopId);

        // Categories: default one + a few random ones + home so the product is browsable
        $categoryIds = $this->entityPicker->getCategoryIds($shopId);
        $picked = $this->entityPicker->pickRandom($categoryIds, max(0, (int) $options['categories_per_product']));
        $homeCategoryId = (int) Configuration::get('PS_HOME_CATEGORY');
        $productCategories = array_values(array_unique(array_merge([$homeCategoryId, (int) $product->id_category_default], $picked)));
        // The CQRS product creation leaves a stale (empty) entry in this cache, which would make
        // updateCategories() re-insert the home category and fail on the primary key
        Cache::clean('Product::getProductCategories_' . $productId);
        $product->updateCategories($productCategories);

        // Brand & supplier (and the product_supplier line the BO expects)
        if (!$options['assign_manufacturer']) {
            $this->connection->update($this->dbPrefix . 'product', ['id_manufacturer' => 0], ['id_product' => $productId]);
        }
        if ($options['assign_supplier'] && (int) $product->id_supplier > 0) {
            $this->connection->executeStatement(sprintf(
                'INSERT IGNORE INTO `%sproduct_supplier` (id_product, id_product_attribute, id_supplier, product_supplier_reference, product_supplier_price_te, id_currency)
                 VALUES (%d, 0, %d, %s, %F, %d)',
                $this->dbPrefix,
                $productId,
                (int) $product->id_supplier,
                $this->connection->quote(strtoupper($this->faker->bothify('SUP-????-####'))),
                (float) $product->wholesale_price,
                (int) Configuration::get('PS_CURRENCY_DEFAULT')
            ));
        } elseif (!$options['assign_supplier']) {
            $this->connection->update($this->dbPrefix . 'product', ['id_supplier' => 0], ['id_product' => $productId]);
        }

        // Stock
        if (!$stockAlreadySet && (int) $options['quantity'] > 0) {
            if (empty($combinationIds)) {
                StockAvailable::setQuantity($productId, 0, (int) $options['quantity'], $shopId, false);
            } else {
                foreach ($combinationIds as $combinationId) {
                    StockAvailable::setQuantity($productId, $combinationId, $this->faker->numberBetween(0, (int) $options['quantity']), $shopId, false);
                }
            }
        }

        // Tags & specific prices
        $this->tagCreator->tagProduct($productId, (int) $options['tags']);
        if ((int) $options['specific_prices'] > 0) {
            $this->specificPriceCreator->createForProduct($productId, (int) $options['specific_prices'], SpecificPriceCreator::TYPE_MIXED, $shopId);
        }

        // Creation date in the past
        if ((int) $options['spread_months'] > 0) {
            $date = $this->dateSpreader->randomPastDate((int) $options['spread_months']);
            $this->connection->update($this->dbPrefix . 'product', ['date_add' => $date, 'date_upd' => $date], ['id_product' => $productId]);
            $this->connection->update($this->dbPrefix . 'product_shop', ['date_add' => $date, 'date_upd' => $date], ['id_product' => $productId]);
        }
    }

    /**
     * Gives random price/weight impacts to some combinations, so they are not all identical to the product.
     *
     * @param int[] $combinationIds
     */
    public function randomizeCombinations(int $productId, array $combinationIds, int $shopId): void
    {
        foreach ($combinationIds as $combinationId) {
            if (!$this->faker->boolean(40)) {
                continue;
            }
            $data = [
                'price' => $this->faker->randomFloat(2, -5, 50),
                'weight' => $this->faker->randomFloat(3, 0, 2),
                'reference' => strtoupper($this->faker->bothify('FAKE-COMB-#####')),
            ];
            $this->connection->update($this->dbPrefix . 'product_attribute', $data, ['id_product_attribute' => $combinationId]);
            $this->connection->update($this->dbPrefix . 'product_attribute_shop', ['price' => $data['price']], ['id_product_attribute' => $combinationId, 'id_shop' => $shopId]);
        }
    }
}
