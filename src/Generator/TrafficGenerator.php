<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\TrafficCreator;

class TrafficGenerator extends AbstractGenerator
{
    private TrafficCreator $trafficCreator;

    public function __construct(TrafficCreator $trafficCreator)
    {
        $this->trafficCreator = $trafficCreator;
    }

    public function getName(): string
    {
        return 'traffic';
    }

    public function getLabel(): string
    {
        return 'Visitors & connections';
    }

    public function getDescription(): string
    {
        return 'Guests, connections, pages viewed and traffic sources: the statistics tables that grow the fastest on a real shop.';
    }

    public function getGroup(): string
    {
        return self::GROUP_TRAFFIC;
    }

    public function getCountLabel(): string
    {
        return 'Number of visitors (guests)';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('connections_per_guest', 'Maximum connections per visitor', 3),
            GeneratorOption::int('pages_per_connection', 'Maximum pages per connection', 5),
            GeneratorOption::int('spread_months', 'Spread dates over (months)', 3),
        ];
    }

    public function getBatchSize(): int
    {
        return 200;
    }

    public function getPriority(): int
    {
        return 110;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $counters = $this->trafficCreator->generate(
            $count,
            (int) $options['connections_per_guest'],
            (int) $options['pages_per_connection'],
            (int) $options['spread_months'],
            $context->getShopId(),
            $context->getShopGroupId()
        );

        $result = new GenerationResult();
        foreach ($counters as $entity => $number) {
            $result->add($entity, $number);
        }

        return $result;
    }
}
