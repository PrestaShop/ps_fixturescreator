<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CustomerThreadCreator;

class CustomerThreadGenerator extends AbstractGenerator
{
    private CustomerThreadCreator $customerThreadCreator;

    public function __construct(CustomerThreadCreator $customerThreadCreator)
    {
        $this->customerThreadCreator = $customerThreadCreator;
    }

    public function getName(): string
    {
        return 'customer_threads';
    }

    public function getLabel(): string
    {
        return 'Customer service threads';
    }

    public function getDescription(): string
    {
        return 'Customer service threads with messages exchanged between customers and employees, linked to orders and products.';
    }

    public function getGroup(): string
    {
        return self::GROUP_SUPPORT;
    }

    public function getCountLabel(): string
    {
        return 'Number of threads';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('messages_per_thread', 'Messages per thread', 3),
            GeneratorOption::bool('link_to_customers', 'Link to existing customers and their orders', true),
            GeneratorOption::int('spread_months', 'Spread dates over (months)', 12),
        ];
    }

    public function getBatchSize(): int
    {
        return 50;
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $counters = $this->customerThreadCreator->generate(
            $count,
            $context->getShopId(),
            (int) $options['messages_per_thread'],
            (bool) $options['link_to_customers'],
            (int) $options['spread_months']
        );

        return (new GenerationResult())
            ->add('customer_threads', $counters['threads'])
            ->add('customer_messages', $counters['messages']);
    }
}
