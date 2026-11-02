<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\ProductCombinationCreator;

class ProductWithCombinationsGenerator extends AbstractGenerator
{
    private ProductCombinationCreator $productCombinationCreator;

    public function __construct(ProductCombinationCreator $productCombinationCreator)
    {
        $this->productCombinationCreator = $productCombinationCreator;
    }

    public function getName(): string
    {
        return 'products_with_combinations';
    }

    public function getLabel(): string
    {
        return 'Products with combinations';
    }

    public function getDescription(): string
    {
        return 'Products declined in combinations (attribute groups × values), each with its own stock. Missing attribute groups are created on the fly.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of products with combinations';
    }

    public function getOptions(): array
    {
        return array_merge([
            GeneratorOption::int('attribute_groups', 'Attribute groups per product', 2),
            GeneratorOption::int('attributes', 'Values per attribute group', 5, '2 groups × 5 values = 25 combinations per product'),
        ], ProductGenerator::commonProductOptions());
    }

    public function getBatchSize(): int
    {
        return 5;
    }

    public function getPriority(): int
    {
        return 41;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $counters = $this->productCombinationCreator->generateWithOptions($count, $options, $context->getShopId());

        $result = new GenerationResult();
        foreach ($counters as $entity => $number) {
            if ($number > 0) {
                $result->add($entity === 'products' ? 'products_with_combinations' : $entity, $number);
            }
        }

        return $result;
    }
}
