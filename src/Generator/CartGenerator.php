<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CartCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\EntityPicker;

class CartGenerator extends AbstractGenerator
{
    private CartCreator $cartCreator;
    private EntityPicker $entityPicker;

    public function __construct(CartCreator $cartCreator, EntityPicker $entityPicker)
    {
        $this->cartCreator = $cartCreator;
        $this->entityPicker = $entityPicker;
    }

    public function getName(): string
    {
        return 'carts';
    }

    public function getLabel(): string
    {
        return 'Abandoned carts';
    }

    public function getDescription(): string
    {
        return 'Carts with random products that never became orders, optionally attached to existing customers.';
    }

    public function getGroup(): string
    {
        return self::GROUP_SALES;
    }

    public function getCountLabel(): string
    {
        return 'Number of carts';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::bool('use_existing_customers', 'Attach to existing customers', true, 'Otherwise carts are anonymous'),
            GeneratorOption::int('products_per_cart', 'Maximum products per cart', 5),
            GeneratorOption::int('spread_months', 'Spread dates over (months)', 3),
        ];
    }

    public function getBatchSize(): int
    {
        return 25;
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $products = $this->entityPicker->getOrderableProducts($context->getShopId());
        $result = new GenerationResult();
        if (empty($products)) {
            return $result->addMessage('No orderable product found: generate products first.');
        }

        $carts = $this->cartCreator->generate(
            $count,
            $products,
            (bool) $options['use_existing_customers'],
            (int) $options['products_per_cart'],
            (int) $options['spread_months'],
            $context->getShopId()
        );

        return $result->add('carts', count($carts));
    }
}
