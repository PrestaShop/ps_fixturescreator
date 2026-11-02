<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

final class GenerationRun
{
    private string $generator;
    private int $count;
    /** @var array<string, mixed> */
    private array $options;
    private GenerationResult $result;
    private int $durationMs;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $generator, int $count, array $options, GenerationResult $result, int $durationMs)
    {
        $this->generator = $generator;
        $this->count = $count;
        $this->options = $options;
        $this->result = $result;
        $this->durationMs = $durationMs;
    }

    public function getGenerator(): string
    {
        return $this->generator;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getResult(): GenerationResult
    {
        return $this->result;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }
}
