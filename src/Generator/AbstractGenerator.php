<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

abstract class AbstractGenerator implements FixtureGeneratorInterface
{
    public function getOptions(): array
    {
        return [];
    }

    public function getBatchSize(): int
    {
        return 25;
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * Fills missing options with their defaults and casts every value to its declared type.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function normalizeOptions(array $options): array
    {
        $normalized = [];
        foreach ($this->getOptions() as $option) {
            $normalized[$option->getName()] = array_key_exists($option->getName(), $options)
                ? $option->normalize($options[$option->getName()])
                : $option->getDefault();
        }

        return $normalized;
    }
}
