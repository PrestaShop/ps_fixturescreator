<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\ProductCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\ProductImageCreator;

class ProductGenerator extends AbstractGenerator
{
    private ProductCreator $productCreator;

    public function __construct(ProductCreator $productCreator)
    {
        $this->productCreator = $productCreator;
    }

    public function getName(): string
    {
        return 'products';
    }

    public function getLabel(): string
    {
        return 'Products';
    }

    public function getDescription(): string
    {
        return 'Standard products with descriptions, price, stock, random categories, brand, supplier, features, tags and optional images.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of products';
    }

    public function getOptions(): array
    {
        return self::commonProductOptions();
    }

    /**
     * @return GeneratorOption[]
     */
    public static function commonProductOptions(): array
    {
        return [
            GeneratorOption::int('categories_per_product', 'Categories per product', 2, 'Random existing categories, in addition to the default one'),
            GeneratorOption::int('features', 'Features per product', 2, 'Missing features are created on the fly'),
            GeneratorOption::int('feature_values', 'Values per feature', 5),
            GeneratorOption::int('tags', 'Tags per product', 3),
            GeneratorOption::int('specific_prices', 'Specific prices per product', 0),
            GeneratorOption::int('images', 'Images per product', 0, 'Each image is resized to every image format: slow but realistic'),
            GeneratorOption::choice('image_source', 'Image source', [ProductImageCreator::SOURCE_LOCAL, ProductImageCreator::SOURCE_PICSUM], ProductImageCreator::SOURCE_LOCAL, 'local = generated with GD (no network), picsum = downloaded photos'),
            GeneratorOption::int('stock_movements', 'Stock movements per product', 0),
            GeneratorOption::int('quantity', 'Stock quantity', 1000),
            GeneratorOption::bool('assign_manufacturer', 'Assign a brand', true),
            GeneratorOption::bool('assign_supplier', 'Assign a supplier', true),
            GeneratorOption::int('spread_months', 'Spread creation dates over (months)', 12),
        ];
    }

    public function getBatchSize(): int
    {
        return 20;
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $counters = $this->productCreator->generateWithOptions($count, $options, $context->getShopId());

        $result = new GenerationResult();
        foreach ($counters as $entity => $number) {
            if ($number > 0) {
                $result->add($entity, $number);
            }
        }

        return $result;
    }
}
