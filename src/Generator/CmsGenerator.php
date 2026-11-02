<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CmsCreator;

class CmsGenerator extends AbstractGenerator
{
    private CmsCreator $cmsCreator;

    public function __construct(CmsCreator $cmsCreator)
    {
        $this->cmsCreator = $cmsCreator;
    }

    public function getName(): string
    {
        return 'cms_pages';
    }

    public function getLabel(): string
    {
        return 'CMS pages';
    }

    public function getDescription(): string
    {
        return 'Multilingual CMS pages with long HTML content, spread over new CMS categories.';
    }

    public function getGroup(): string
    {
        return self::GROUP_CONTENT;
    }

    public function getCountLabel(): string
    {
        return 'Number of CMS pages';
    }

    public function getOptions(): array
    {
        return [
            GeneratorOption::int('cms_categories', 'CMS categories to create', 3),
        ];
    }

    public function getBatchSize(): int
    {
        return 50;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $counters = $this->cmsCreator->generate($count, (int) $options['cms_categories'], $context->getShopId());

        return (new GenerationResult())
            ->add('cms_pages', $counters['cms_pages'])
            ->add('cms_categories', $counters['cms_categories']);
    }
}
