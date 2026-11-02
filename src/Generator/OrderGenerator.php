<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\EntityPicker;
use PrestaShop\Module\PsFixturesCreator\Creator\OrderCreator;

class OrderGenerator extends AbstractGenerator
{
    private OrderCreator $orderCreator;
    private EntityPicker $entityPicker;

    public function __construct(OrderCreator $orderCreator, EntityPicker $entityPicker)
    {
        $this->orderCreator = $orderCreator;
        $this->entityPicker = $entityPicker;
    }

    public function getName(): string
    {
        return 'orders';
    }

    public function getLabel(): string
    {
        return 'Orders';
    }

    public function getDescription(): string
    {
        return 'Complete orders: cart, order lines, carrier, status history (paid, shipped, delivered, refunded...), payment and invoice. Stock is decremented like a real sale.';
    }

    public function getGroup(): string
    {
        return self::GROUP_SALES;
    }

    public function getCountLabel(): string
    {
        return 'Number of orders';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::bool('use_existing_customers', 'Use existing customers', true, 'Otherwise a new customer is created for each order'),
            GeneratorOption::int('products_per_order', 'Maximum products per order', 5),
            GeneratorOption::bool('with_invoice', 'Generate invoices', true),
            GeneratorOption::int('spread_months', 'Spread order dates over (months)', 12),
        ];
    }

    public function getBatchSize(): int
    {
        return 10;
    }

    public function getPriority(): int
    {
        return 81;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $products = $this->entityPicker->getOrderableProducts($context->getShopId());
        $result = new GenerationResult();
        if (empty($products)) {
            return $result->addMessage('No orderable product found: generate products first.');
        }

        $counters = $this->orderCreator->generate(
            $count,
            $context->getShopGroupId(),
            $context->getShopId(),
            $products,
            (bool) $options['use_existing_customers'],
            (int) $options['products_per_order'],
            (bool) $options['with_invoice'],
            (int) $options['spread_months']
        );

        foreach ($counters as $entity => $number) {
            if ($number > 0 && 'skipped' !== $entity) {
                $result->add($entity, $number);
            }
        }
        if ($counters['skipped'] > 0) {
            $result->addMessage(sprintf('%d order(s) skipped because no product could be added to the cart (out of stock?).', $counters['skipped']));
        }

        return $result;
    }
}
