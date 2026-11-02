<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use CMS;
use CMSCategory;
use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Tools;

class CmsCreator
{
    private Faker $faker;
    private LangRepository $langRepository;
    private EntityPicker $entityPicker;

    public function __construct(Faker $faker, LangRepository $langRepository, EntityPicker $entityPicker)
    {
        $this->faker = $faker;
        $this->langRepository = $langRepository;
        $this->entityPicker = $entityPicker;
    }

    /**
     * @return array{cms_categories: int, cms_pages: int}
     */
    public function generate(int $pagesNumber, int $categoriesNumber, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $counters = ['cms_categories' => 0, 'cms_pages' => 0];

        for ($i = 0; $i < $categoriesNumber; ++$i) {
            $this->createCategory($languages, $shopId);
            ++$counters['cms_categories'];
        }

        $categoryIds = $this->entityPicker->getCmsCategoryIds();
        if (empty($categoryIds)) {
            $categoryIds = [1];
        }

        for ($i = 0; $i < $pagesNumber; ++$i) {
            $this->createPage($languages, (int) $categoryIds[array_rand($categoryIds)], $shopId);
            ++$counters['cms_pages'];
        }

        return $counters;
    }

    /**
     * @param Lang[] $languages
     */
    private function createCategory(array $languages, int $shopId): CMSCategory
    {
        $baseName = ucfirst($this->faker->words(2, true));

        $category = new CMSCategory();
        $category->id_parent = 1;
        $category->active = true;
        $category->id_shop_list = [$shopId];
        $names = [];
        $linkRewrites = [];
        $descriptions = [];
        $metaTitles = [];
        $metaDescriptions = [];
        foreach ($languages as $lang) {
            $name = $baseName . ' ' . $lang->getLocale();
            $names[$lang->getId()] = $name;
            $linkRewrites[$lang->getId()] = Tools::str2url($name . '-' . uniqid());
            $descriptions[$lang->getId()] = $this->faker->sentence(10);
            $metaTitles[$lang->getId()] = $name;
            $metaDescriptions[$lang->getId()] = $this->faker->sentence(8);
        }
        $category->name = $names;
        $category->link_rewrite = $linkRewrites;
        $category->description = $descriptions;
        $category->meta_title = $metaTitles;
        $category->meta_description = $metaDescriptions;
        $category->add();

        return $category;
    }

    /**
     * @param Lang[] $languages
     */
    private function createPage(array $languages, int $categoryId, int $shopId): CMS
    {
        $baseTitle = ucfirst($this->faker->catchPhrase);

        $cms = new CMS();
        $cms->id_cms_category = $categoryId;
        $cms->active = $this->faker->boolean(90);
        $cms->indexation = true;
        $cms->id_shop_list = [$shopId];
        $metaTitles = [];
        $headSeoTitles = [];
        $linkRewrites = [];
        $contents = [];
        $metaDescriptions = [];
        foreach ($languages as $lang) {
            $title = $baseTitle . ' ' . $lang->getLocale();
            $metaTitles[$lang->getId()] = $title;
            $headSeoTitles[$lang->getId()] = $title;
            $linkRewrites[$lang->getId()] = Tools::str2url($title . '-' . uniqid());
            $contents[$lang->getId()] = '<h2>' . $this->faker->sentence(5) . '</h2><p>' . implode('</p><p>', $this->faker->paragraphs($this->faker->numberBetween(3, 8))) . '</p>';
            $metaDescriptions[$lang->getId()] = $this->faker->sentence(10);
        }
        $cms->meta_title = $metaTitles;
        $cms->head_seo_title = $headSeoTitles;
        $cms->link_rewrite = $linkRewrites;
        $cms->content = $contents;
        $cms->meta_description = $metaDescriptions;
        $cms->add();

        return $cms;
    }
}
