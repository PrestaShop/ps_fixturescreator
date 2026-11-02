<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CustomerCreator;

class CustomerGenerator extends AbstractGenerator
{
    private CustomerCreator $customerCreator;

    public function __construct(CustomerCreator $customerCreator)
    {
        $this->customerCreator = $customerCreator;
    }

    public function getName(): string
    {
        return 'customers';
    }

    public function getLabel(): string
    {
        return 'Customers';
    }

    public function getDescription(): string
    {
        return 'Registered customers (password: "prestashop") with addresses, optionally assigned to random groups, spread over time.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CUSTOMERS;
    }

    public function getCountLabel(): string
    {
        return 'Number of customers';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('addresses_per_customer', 'Addresses per customer', 1),
            GeneratorOption::bool('random_group', 'Assign a random customer group', true),
            GeneratorOption::int('spread_months', 'Spread registration dates over (months)', 24),
        ];
    }

    public function getBatchSize(): int
    {
        return 50;
    }

    public function getPriority(): int
    {
        return 61;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $customers = $this->customerCreator->generate(
            $count,
            (int) $options['addresses_per_customer'],
            (bool) $options['random_group'],
            (int) $options['spread_months']
        );

        return (new GenerationResult())
            ->add('customers', count($customers))
            ->add('addresses', count($customers) * (int) $options['addresses_per_customer']);
    }
}
