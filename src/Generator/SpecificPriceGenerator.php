<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\SpecificPriceCreator;

class SpecificPriceGenerator extends AbstractGenerator
{
    private SpecificPriceCreator $specificPriceCreator;

    public function __construct(SpecificPriceCreator $specificPriceCreator)
    {
        $this->specificPriceCreator = $specificPriceCreator;
    }

    public function getName(): string
    {
        return 'specific_prices';
    }

    public function getLabel(): string
    {
        return 'Specific prices';
    }

    public function getDescription(): string
    {
        return 'Discounts on random existing products (by quantity, group, country, date range). Heavy impact on price computation in listings and carts.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of specific prices';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::choice('type', 'Reduction type', [SpecificPriceCreator::TYPE_MIXED, SpecificPriceCreator::TYPE_AMOUNT, SpecificPriceCreator::TYPE_PERCENTAGE], SpecificPriceCreator::TYPE_MIXED),
        ];
    }

    public function getBatchSize(): int
    {
        return 200;
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $created = $this->specificPriceCreator->generate($count, (string) $options['type'], $context->getShopId());

        return (new GenerationResult())->add('specific_prices', $created);
    }
}
