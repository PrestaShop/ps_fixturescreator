<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\EmployeeCreator;

class EmployeeGenerator extends AbstractGenerator
{
    private EmployeeCreator $employeeCreator;

    public function __construct(EmployeeCreator $employeeCreator)
    {
        $this->employeeCreator = $employeeCreator;
    }

    public function getName(): string
    {
        return 'employees';
    }

    public function getLabel(): string
    {
        return 'Employees';
    }

    public function getDescription(): string
    {
        return 'Back office employees (password: "prestashop_fixtures") with random non-admin profiles.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CONTENT;
    }

    public function getCountLabel(): string
    {
        return 'Number of employees';
    }

    public function getBatchSize(): int
    {
        return 50;
    }

    public function getPriority(): int
    {
        return 102;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->employeeCreator->generate($count, $context->getLangId(), $context->getShopId());

        return (new GenerationResult())->add('employees', count($ids));
    }
}
