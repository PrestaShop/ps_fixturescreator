<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Statistics\RunLogger;

/**
 * Single entry point used by both the CLI and the back office to run a generator:
 * normalizes options, prepares the legacy context, measures the duration and logs the run.
 */
class GenerationRunner
{
    private GeneratorRegistry $registry;
    private RunLogger $runLogger;
    private LegacyContextPreparer $contextPreparer;

    public function __construct(GeneratorRegistry $registry, RunLogger $runLogger, LegacyContextPreparer $contextPreparer)
    {
        $this->registry = $registry;
        $this->runLogger = $runLogger;
        $this->contextPreparer = $contextPreparer;
    }

    /**
     * @param array<string, mixed> $rawOptions
     */
    public function run(string $generatorName, int $count, array $rawOptions, GenerationContext $context, string $source): GenerationRun
    {
        $generator = $this->registry->get($generatorName);
        $options = $this->registry->normalizeOptions($generatorName, $rawOptions);

        $this->contextPreparer->prepare($context);

        $start = microtime(true);
        $result = $generator->generate($count, $options, $context);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->runLogger->log($generatorName, $count, $options, $result, $durationMs, $source, $context->getShopId());

        return new GenerationRun($generatorName, $count, $options, $result, $durationMs);
    }
}
