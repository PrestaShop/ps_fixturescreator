<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\FeatureCreator;

class FeatureGenerator extends AbstractGenerator
{
    private FeatureCreator $featureCreator;

    public function __construct(FeatureCreator $featureCreator)
    {
        $this->featureCreator = $featureCreator;
    }

    public function getName(): string
    {
        return 'features';
    }

    public function getLabel(): string
    {
        return 'Features';
    }

    public function getDescription(): string
    {
        return 'Product features (composition, style...) with their values. Used by faceted search and product comparison.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CATALOG;
    }

    public function getCountLabel(): string
    {
        return 'Number of features';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('values_per_feature', 'Values per feature', 10),
        ];
    }

    public function getBatchSize(): int
    {
        return 10;
    }

    public function getPriority(): int
    {
        return 31;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $features = $this->featureCreator->generate($count, (int) $options['values_per_feature'], $context->getShopId());

        return (new GenerationResult())
            ->add('features', count($features))
            ->add('feature_values', count($features) * (int) $options['values_per_feature']);
    }
}
