<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

class GeneratorRegistry
{
    /** @var array<string, FixtureGeneratorInterface> */
    private array $generators = [];

    /**
     * @param iterable<FixtureGeneratorInterface> $generators
     */
    public function __construct(iterable $generators)
    {
        foreach ($generators as $generator) {
            $this->generators[$generator->getName()] = $generator;
        }
        uasort($this->generators, static function (FixtureGeneratorInterface $a, FixtureGeneratorInterface $b): int {
            return $a->getPriority() <=> $b->getPriority();
        });
    }

    public function has(string $name): bool
    {
        return isset($this->generators[$name]);
    }

    public function get(string $name): FixtureGeneratorInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('Unknown generator "%s". Available generators: %s', $name, implode(', ', array_keys($this->generators))));
        }

        return $this->generators[$name];
    }

    /**
     * @return array<string, FixtureGeneratorInterface> sorted by priority
     */
    public function all(): array
    {
        return $this->generators;
    }

    /**
     * @return array<string, array<string, FixtureGeneratorInterface>> generators indexed by group then name
     */
    public function grouped(): array
    {
        $grouped = [];
        foreach ($this->generators as $name => $generator) {
            $grouped[$generator->getGroup()][$name] = $generator;
        }

        return $grouped;
    }

    /**
     * Normalizes raw options for the given generator, falling back to defaults.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function normalizeOptions(string $name, array $options): array
    {
        $generator = $this->get($name);
        $normalized = [];
        foreach ($generator->getOptions() as $option) {
            $normalized[$option->getName()] = array_key_exists($option->getName(), $options)
                ? $option->normalize($options[$option->getName()])
                : $option->getDefault();
        }

        return $normalized;
    }

    /**
     * Serializable description of every generator, used by the back office UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function describe(): array
    {
        $described = [];
        foreach ($this->generators as $generator) {
            $described[] = [
                'name' => $generator->getName(),
                'label' => $generator->getLabel(),
                'description' => $generator->getDescription(),
                'group' => $generator->getGroup(),
                'countLabel' => $generator->getCountLabel(),
                'batchSize' => $generator->getBatchSize(),
                'priority' => $generator->getPriority(),
                'options' => array_map(static function (GeneratorOption $option): array {
                    return $option->toArray();
                }, $generator->getOptions()),
            ];
        }

        return $described;
    }
}
