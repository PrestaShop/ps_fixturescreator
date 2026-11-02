<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;
use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Product;

class ProductCreator extends AbstractProductCreator
{
    private LangRepository $langRepository;
    private EntityPicker $entityPicker;

    public function __construct(
        LangRepository $langRepository,
        FeatureCreator $featureCreator,
        Connection $connection,
        string $dbPrefix,
        Faker $faker,
        StockMovementCreator $stockMovementCreator,
        ProductImageCreator $productImageCreator,
        ProductEnricher $productEnricher,
        EntityPicker $entityPicker
    ) {
        parent::__construct($featureCreator, $productImageCreator, $stockMovementCreator, $productEnricher, $connection, $faker, $dbPrefix);
        $this->langRepository = $langRepository;
        $this->entityPicker = $entityPicker;
    }

    /**
     * Legacy signature kept for the prestashop:product-creator command.
     */
    public function generate(
        int $number,
        int $numberOfFeatures,
        int $numberOfFeatureValues,
        int $numberOfStockMovements,
        int $numberOfImages,
        int $shopId
    ): void {
        $this->generateWithOptions($number, [
            'features' => $numberOfFeatures,
            'feature_values' => $numberOfFeatureValues,
            'stock_movements' => $numberOfStockMovements,
            'images' => $numberOfImages,
        ], $shopId);
    }

    /**
     * @param array<string, mixed> $options see AbstractProductCreator::getDefaultOptions()
     *
     * @return array<string, int> counters
     */
    public function generateWithOptions(int $number, array $options, int $shopId): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);
        $counters = ['products' => 0, 'images' => 0, 'stock_movements' => 0, 'feature_links' => 0];

        for ($i = 0; $i < $number; ++$i) {
            $productId = $this->createProduct($shopId);
            ++$counters['products'];
            $decorated = $this->decorateProduct($productId, [], $options, $shopId);
            foreach ($decorated as $key => $value) {
                $counters[$key] += $value;
            }
        }
        $this->entityPicker->invalidate('product');

        return $counters;
    }

    private function createProduct(int $shopId): int
    {
        $product = new Product();
        $product->id_shop_list = [$shopId];
        $productName = $this->faker->productName;

        $productNames = [];
        /** @var Lang $lang */
        foreach ($this->langRepository->findAll() as $lang) {
            $productNames[$lang->getId()] = $productName . ' ' . $lang->getLocale();
        }

        $this->productEnricher->fillBaseFields($product, $productNames, $shopId);
        $product->product_type = 'standard';
        $product->add();

        return (int) $product->id;
    }
}
