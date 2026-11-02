<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CustomerGroupCreator;

class CustomerGroupGenerator extends AbstractGenerator
{
    private CustomerGroupCreator $customerGroupCreator;

    public function __construct(CustomerGroupCreator $customerGroupCreator)
    {
        $this->customerGroupCreator = $customerGroupCreator;
    }

    public function getName(): string
    {
        return 'customer_groups';
    }

    public function getLabel(): string
    {
        return 'Customer groups';
    }

    public function getDescription(): string
    {
        return 'Customer groups with random reductions. Every group multiplies price rules, category restrictions and module permissions.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CUSTOMERS;
    }

    public function getCountLabel(): string
    {
        return 'Number of customer groups';
    }

    public function getBatchSize(): int
    {
        return 20;
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->customerGroupCreator->generate($count, $context->getShopId());

        return (new GenerationResult())->add('groups', count($ids));
    }
}
