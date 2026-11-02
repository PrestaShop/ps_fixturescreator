<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\SupplierCreator;

class SupplierGenerator extends AbstractGenerator
{
    private SupplierCreator $supplierCreator;

    public function __construct(SupplierCreator $supplierCreator)
    {
        $this->supplierCreator = $supplierCreator;
    }

    public function getName(): string
    {
        return 'suppliers';
    }

    public function getLabel(): string
    {
        return 'Suppliers';
    }

    public function getDescription(): string
    {
        return 'Suppliers with multilingual descriptions. Generated products are randomly attached to existing suppliers.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of suppliers';
    }

    public function getBatchSize(): int
    {
        return 100;
    }

    public function getPriority(): int
    {
        return 21;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->supplierCreator->generate($count, $context->getShopId());

        return (new GenerationResult())->add('suppliers', count($ids));
    }
}
