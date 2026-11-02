<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\AttributeCreator;

class AttributeGenerator extends AbstractGenerator
{
    private AttributeCreator $attributeCreator;

    public function __construct(AttributeCreator $attributeCreator)
    {
        $this->attributeCreator = $attributeCreator;
    }

    public function getName(): string
    {
        return 'attributes';
    }

    public function getLabel(): string
    {
        return 'Attribute groups';
    }

    public function getDescription(): string
    {
        return 'Attribute groups (size, color...) with their values, used to build product combinations.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of attribute groups';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('values_per_group', 'Values per group', 10),
        ];
    }

    public function getBatchSize(): int
    {
        return 10;
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $groups = $this->attributeCreator->generate($count, (int) $options['values_per_group'], $context->getShopId());

        return (new GenerationResult())
            ->add('attribute_groups', count($groups))
            ->add('attributes', count($groups) * (int) $options['values_per_group']);
    }
}
