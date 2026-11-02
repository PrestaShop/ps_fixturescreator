<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\StoreCreator;

class StoreGenerator extends AbstractGenerator
{
    private StoreCreator $storeCreator;

    public function __construct(StoreCreator $storeCreator)
    {
        $this->storeCreator = $storeCreator;
    }

    public function getName(): string
    {
        return 'stores';
    }

    public function getLabel(): string
    {
        return 'Physical stores';
    }

    public function getDescription(): string
    {
        return 'Physical stores (store locator) with address, coordinates and opening hours.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CONTENT;
    }

    public function getCountLabel(): string
    {
        return 'Number of stores';
    }

    public function getBatchSize(): int
    {
        return 50;
    }

    public function getPriority(): int
    {
        return 101;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->storeCreator->generate($count, $context->getShopId());

        return (new GenerationResult())->add('stores', count($ids));
    }
}
