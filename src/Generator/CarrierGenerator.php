<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CarrierCreator;

class CarrierGenerator extends AbstractGenerator
{
    private CarrierCreator $carrierCreator;

    public function __construct(CarrierCreator $carrierCreator)
    {
        $this->carrierCreator = $carrierCreator;
    }

    public function getName(): string
    {
        return 'carriers';
    }

    public function getLabel(): string
    {
        return 'Carriers';
    }

    public function getDescription(): string
    {
        return 'Carriers available in every zone and for every group, with price and weight ranges. Generated orders pick a random eligible carrier.';
    }

    public function getGroup(): string
    {
        return self::GROUP_SALES;
    }

    public function getCountLabel(): string
    {
        return 'Number of carriers';
    }

    public function getBatchSize(): int
    {
        return 20;
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $carriers = $this->carrierCreator->generate($count, $context->getLangId());

        return (new GenerationResult())->add('carriers', count($carriers));
    }
}
