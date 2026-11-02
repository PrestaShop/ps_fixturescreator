<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\ManufacturerCreator;

class ManufacturerGenerator extends AbstractGenerator
{
    private ManufacturerCreator $manufacturerCreator;

    public function __construct(ManufacturerCreator $manufacturerCreator)
    {
        $this->manufacturerCreator = $manufacturerCreator;
    }

    public function getName(): string
    {
        return 'manufacturers';
    }

    public function getLabel(): string
    {
        return 'Brands';
    }

    public function getDescription(): string
    {
        return 'Brands (manufacturers) with multilingual descriptions. Generated products are randomly attached to existing brands.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of brands';
    }

    public function getBatchSize(): int
    {
        return 100;
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->manufacturerCreator->generate($count, $context->getShopId());

        return (new GenerationResult())->add('manufacturers', count($ids));
    }
}
