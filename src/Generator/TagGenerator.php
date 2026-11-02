<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\TagCreator;

class TagGenerator extends AbstractGenerator
{
    private TagCreator $tagCreator;

    public function __construct(TagCreator $tagCreator)
    {
        $this->tagCreator = $tagCreator;
    }

    public function getName(): string
    {
        return 'tags';
    }

    public function getLabel(): string
    {
        return 'Product tags';
    }

    public function getDescription(): string
    {
        return 'Tags random existing products with words from a shared vocabulary, in every language.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of products to tag';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('tags_per_product', 'Tags per product', 3),
        ];
    }

    public function getBatchSize(): int
    {
        return 100;
    }

    public function getPriority(): int
    {
        return 51;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $created = $this->tagCreator->generate($count, (int) $options['tags_per_product'], $context->getShopId());

        return (new GenerationResult())->add('product_tags', $created);
    }
}
