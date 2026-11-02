<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Category;
use Configuration;
use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Tools;

/**
 * Builds a random category tree under the home category (or any given parent).
 * The nested set (nleft/nright) is regenerated once at the end, not for each category.
 */
class CategoryCreator
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
     * @param int $number total number of categories to create
     * @param int $maxDepth maximum depth below the parent (1 = flat list)
     * @param int $parentId parent category id, 0 for the shop home category
     *
     * @return int[] created category ids
     */
    public function generate(int $number, int $maxDepth, int $parentId, int $shopId): array
    {
        if ($number <= 0) {
            return [];
        }

        if ($parentId <= 0) {
            $parentId = (int) Configuration::get('PS_HOME_CATEGORY');
        }
        $parent = new Category($parentId);
        $parentDepth = (int) $parent->level_depth;
        $maxDepth = max(1, $maxDepth);

        $languages = $this->langRepository->findAll();

        // Candidates to become a parent: [id => depth relative to the root parent]
        $candidates = [$parentId => 0];
        $createdIds = [];

        for ($i = 0; $i < $number; ++$i) {
            // Prefer shallow parents so the tree gets wide and deep at the same time
            $chosenParentId = $this->pickParent($candidates, $maxDepth);
            $relativeDepth = $candidates[$chosenParentId] + 1;

            $category = $this->createCategory($chosenParentId, $parentDepth + $relativeDepth, $languages, $shopId);
            $createdIds[] = (int) $category->id;

            if ($relativeDepth < $maxDepth) {
                $candidates[(int) $category->id] = $relativeDepth;
            }
        }

        Category::regenerateEntireNtree();
        $this->entityPicker->invalidate('category');

        return $createdIds;
    }

    /**
     * @param array<int, int> $candidates
     */
    private function pickParent(array $candidates, int $maxDepth): int
    {
        $ids = array_keys($candidates);
        // ~30% of the time attach to the root parent so the first level stays well populated
        if ($this->faker->boolean(30)) {
            return $ids[0];
        }

        return $ids[array_rand($ids)];
    }

    /**
     * @param Lang[] $languages
     */
    private function createCategory(int $parentId, int $levelDepth, array $languages, int $shopId): Category
    {
        $baseName = ucfirst($this->faker->department(3));

        $category = new Category();
        $category->doNotRegenerateNTree = true;
        $category->id_parent = $parentId;
        $category->level_depth = $levelDepth;
        $category->active = true;
        $category->id_shop_default = $shopId;
        $category->id_shop_list = [$shopId];
        $category->is_root_category = false;

        $names = [];
        $linkRewrites = [];
        $descriptions = [];
        $metaTitles = [];
        $metaDescriptions = [];
        foreach ($languages as $lang) {
            $name = $baseName . ' ' . $lang->getLocale();
            $names[$lang->getId()] = $name;
            $linkRewrites[$lang->getId()] = Tools::str2url($name . '-' . uniqid());
            $descriptions[$lang->getId()] = '<p>' . $this->faker->sentence(12) . '</p>';
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
}
