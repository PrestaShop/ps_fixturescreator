<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Feature;
use FeatureValue;
use PrestaShop\Module\PsFixturesCreator\Faker\FakerCategory;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;

class FeatureCreator
{
    private LangRepository $langRepository;

    public function __construct(
        LangRepository $langRepository,
    ) {
        $this->langRepository = $langRepository;
    }

    public function generate(int $featuresNumber, int $valuesPerFeatureNumber, int $shopId): array
    {
        $languages = $this->langRepository->findAll();

        $generatedFeatures = [];
        for ($i = 1; $i <= $featuresNumber; ++$i) {
            $fakerCategory = FakerCategory::getCategory();
            $feature = $this->createFeature($fakerCategory, $languages, $shopId);
            for ($j = 1; $j <= $valuesPerFeatureNumber; ++$j) {
                $this->createFeatureValue($feature, $fakerCategory, $languages, $shopId);
            }
        }

        return $generatedFeatures;
    }

    /**
     * @param FakerCategory $fakerCategory
     * @param Lang[] $languages
     * @param int $shopId
     *
     * @return Feature
     */
    private function createFeature(FakerCategory $fakerCategory, array $languages, int $shopId): Feature
    {
        $feature = new Feature();
        $feature->id_shop_list = [$shopId];
        $names = [];
        foreach ($languages as $lang) {
            $names[$lang->getId()] = $fakerCategory->getCategoryName() . ' ' . $lang->getLocale();
        }
        $feature->name = $names;
        $feature->add();

        return $feature;
    }

    /**
     * @param Feature $feature
     * @param FakerCategory $fakerCategory
     * @param Lang[] $languages
     * @param int $shopId
     */
    private function createFeatureValue(Feature $feature, FakerCategory $fakerCategory, array $languages, int $shopId): void
    {
        $featureValue = new FeatureValue();
        $featureValue->id_feature = $feature->id;
        $featureValue->id_shop_list = [$shopId];
        $values = [];
        foreach ($languages as $lang) {
            $values[$lang->getId()] = $fakerCategory->getCategoryValue($lang->getLocale());
        }
        $featureValue->value = $values;
        $featureValue->add();
    }
}
