<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

interface FixtureGeneratorInterface
{
    public const GROUP_CATALOG = 'catalog';
    public const GROUP_CUSTOMERS = 'customers';
    public const GROUP_SALES = 'sales';
    public const GROUP_SUPPORT = 'support';
    public const GROUP_CONTENT = 'content';
    public const GROUP_TRAFFIC = 'traffic';

    /**
     * Unique machine name (snake_case), used on the CLI and in the UI.
     */
    public function getName(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    /**
     * One of the GROUP_* constants, used to group generators in the UI.
     */
    public function getGroup(): string;

    /**
     * Label of the main "count" input (e.g. "Number of products").
     */
    public function getCountLabel(): string;

    /**
     * @return GeneratorOption[]
     */
    public function getOptions(): array;

    /**
     * Recommended number of items per HTTP request when running from the back office.
     */
    public function getBatchSize(): int;

    /**
     * Lower value = executed first when several generators run in one go.
     */
    public function getPriority(): int;

    /**
     * @param int $count number of main entities to create
     * @param array<string, mixed> $options normalized options (see GeneratorOption::normalize)
     */
    public function generate(int $count, array $options, GenerationContext $context): GenerationResult;
}
