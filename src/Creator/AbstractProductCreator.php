<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;
use Faker\Generator as Faker;

abstract class AbstractProductCreator
{
    protected FeatureCreator $featureCreator;
    protected ProductImageCreator $productImageCreator;
    protected StockMovementCreator $stockMovementCreator;
    protected ProductEnricher $productEnricher;
    protected Connection $connection;
    protected string $dbPrefix;
    protected Faker $faker;

    public function __construct(
        FeatureCreator $featureCreator,
        ProductImageCreator $productImageCreator,
        StockMovementCreator $stockMovementCreator,
        ProductEnricher $productEnricher,
        Connection $connection,
        Faker $faker,
        string $dbPrefix
    ) {
        $this->featureCreator = $featureCreator;
        $this->productImageCreator = $productImageCreator;
        $this->stockMovementCreator = $stockMovementCreator;
        $this->productEnricher = $productEnricher;
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
        $this->faker = $faker;
    }

    /**
     * Options shared by simple products and products with combinations.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultOptions(): array
    {
        return array_merge(ProductEnricher::getDefaultOptions(), [
            'features' => 2,
            'feature_values' => 5,
            'stock_movements' => 0,
            'images' => 0,
            'image_source' => ProductImageCreator::SOURCE_LOCAL,
        ]);
    }

    /**
     * @param int[] $combinationIds
     * @param array<string, mixed> $options
     *
     * @return array<string, int> counters
     */
    protected function decorateProduct(int $productId, array $combinationIds, array $options, int $shopId): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);
        $counters = ['images' => 0, 'stock_movements' => 0, 'feature_links' => 0];

        $this->productImageCreator->setSource((string) $options['image_source']);
        $counters['images'] = $this->associateImages($productId, $combinationIds, (int) $options['images']);

        $stockMovements = (int) $options['stock_movements'];
        if ($stockMovements > 0) {
            $this->associateStockMovements($productId, empty($combinationIds) ? [null] : $combinationIds, $stockMovements);
            $counters['stock_movements'] = $stockMovements * max(1, count($combinationIds));
        }

        $counters['feature_links'] = $this->associateFeatures($productId, (int) $options['features'], (int) $options['feature_values'], $shopId);

        $this->productEnricher->enrich($productId, $combinationIds, $options, $shopId, $stockMovements > 0);

        return $counters;
    }

    protected function associateFeatures(int $productId, int $numberOfFeatures, int $numberOfFeatureValues, int $shopId): int
    {
        if ($numberOfFeatures <= 0 || $numberOfFeatureValues <= 0) {
            return 0;
        }

        $featureIds = $this->getFeatureWithAtLeast($numberOfFeatureValues);
        // Create the missing features if needed
        if (count($featureIds) < $numberOfFeatures) {
            $generatedFeatures = $this->featureCreator->generate($numberOfFeatures - count($featureIds), $numberOfFeatureValues, $shopId);
            foreach ($generatedFeatures as $feature) {
                $featureIds[] = (int) $feature->id;
            }
        }

        // Use at most $numberOfFeatures features for this product, picked randomly
        if (count($featureIds) > $numberOfFeatures) {
            $featureIds = array_intersect_key($featureIds, array_flip((array) array_rand($featureIds, $numberOfFeatures)));
        }

        $links = 0;
        foreach ($featureIds as $featureId) {
            // A product usually has one value per feature, sometimes a couple
            $valuesCount = $this->faker->boolean(80) ? 1 : min(2, $numberOfFeatureValues);
            $randomFeatureValueIds = $this->getRandomValues($featureId, $valuesCount);
            $this->associateFeatureValues($productId, $featureId, $randomFeatureValueIds);
            $links += count($randomFeatureValueIds);
        }

        return $links;
    }

    protected function associateFeatureValues(int $productId, int $featureId, array $featureValueIds): void
    {
        foreach ($featureValueIds as $featureValueId) {
            $insertedValues = [
                'id_product' => $productId,
                'id_feature' => $featureId,
                'id_feature_value' => $featureValueId,
            ];
            $this->connection->insert($this->dbPrefix . 'feature_product', $insertedValues);
        }
    }

    protected function associateStockMovements(int $productId, array $combinationsId, int $numberOfStockMovements): void
    {
        if ($numberOfStockMovements <= 0) {
            return;
        }

        $this->stockMovementCreator->generate($numberOfStockMovements, $productId, $combinationsId);
    }

    protected function associateImages(int $productId, array $combinationsId, int $numberOfImages): int
    {
        if ($numberOfImages <= 0) {
            return 0;
        }

        return $this->productImageCreator->generate($numberOfImages, $productId, $combinationsId);
    }

    protected function getRandomValues(int $featureId, int $numberOfFeatureValues): array
    {
        $featureValueIds = $this->connection->createQueryBuilder()
            ->select('fv.id_feature_value')
            ->from($this->dbPrefix . 'feature_value', 'fv')
            ->where('fv.id_feature = :featureId')
            ->setParameter('featureId', $featureId)
            ->execute()
            ->fetchAllAssociative()
        ;

        if (empty($featureValueIds)) {
            return [];
        }

        $randomKeys = (array) array_rand($featureValueIds, min($numberOfFeatureValues, count($featureValueIds)));
        $randomFeatureValueIds = [];
        foreach ($randomKeys as $randomKey) {
            $randomFeatureValueIds[] = (int) $featureValueIds[$randomKey]['id_feature_value'];
        }

        return $randomFeatureValueIds;
    }

    protected function getFeatureWithAtLeast(int $minimumValuesNumber): array
    {
        $features = $this->connection->createQueryBuilder()
            ->select('f.id_feature, COUNT(fv.id_feature_value) AS featureValuesNb')
            ->from($this->dbPrefix . 'feature', 'f')
            ->leftJoin(
                'f',
                $this->dbPrefix . 'feature_value',
                'fv',
                'f.id_feature = fv.id_feature'
            )
            ->addGroupBy('f.id_feature')
            ->execute()
            ->fetchAllAssociative()
        ;

        $featureIds = array_map(static function (array $feature) {
            return (int) $feature['id_feature'];
        }, array_filter($features, static function (array $feature) use ($minimumValuesNumber) {
            return $feature['featureValuesNb'] >= $minimumValuesNumber;
        }));

        return array_values($featureIds);
    }
}
