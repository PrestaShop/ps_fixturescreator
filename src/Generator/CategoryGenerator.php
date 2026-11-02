<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CategoryCreator;

class CategoryGenerator extends AbstractGenerator
{
    private CategoryCreator $categoryCreator;

    public function __construct(CategoryCreator $categoryCreator)
    {
        $this->categoryCreator = $categoryCreator;
    }

    public function getName(): string
    {
        return 'categories';
    }

    public function getLabel(): string
    {
        return 'Categories';
    }

    public function getDescription(): string
    {
        return 'Random category tree under the home category. Deep trees stress the nested set regeneration, the category menu and breadcrumbs.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of categories';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('depth', 'Maximum depth', 3, 'Levels below the parent category (1 = flat list)'),
            GeneratorOption::int('parent', 'Parent category id', 0, '0 = home category of the shop'),
        ];
    }

    public function getBatchSize(): int
    {
        return 100;
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $ids = $this->categoryCreator->generate($count, (int) $options['depth'], (int) $options['parent'], $context->getShopId());

        return (new GenerationResult())->add('categories', count($ids));
    }
}
