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

namespace PrestaShop\Module\PsFixturesCreator\Faker;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Faker\Generator;

class FakerCategory
{
    /**
     * All the available categories are actually different providers from the Bezhanov provider collection
     */
    public const AVAILABLE_CATEGORIES = [
        'department',
        'deviceModelName',
        'devicePlatform',
        'university',
        'secondarySchool',
        'course',
        'campus',
        'ingredient',
        'spice',
        'measurement',
        'medicine',
        'chemicalElement',
        'planet',
        'galaxy',
        'constellation',
        'meteorite',
        'bird',
        'creature',
        'plant',
        'team',
    ];

    /**
     * Locale static array to avoid generating the same category too often, we perform a cycle through
     * all available categories. Then we reinitialize the available categories when they all were used once.
     *
     * @var array
     */
    private static array $randomAvailableCategories;

    /**
     * @var Inflector|null
     */
    private static $inflector = null;

    private string $category;
    private string $categoryName;

    /**
     * @var Generator[]
     */
    private array $localizedGenerators;

    public static function getCategory(string $category = null): self
    {
        // Choose a random category
        if (null === $category) {
            // When remaining random categories are empty we fill it up again
            if (empty(self::$randomAvailableCategories)) {
                self::$randomAvailableCategories = self::AVAILABLE_CATEGORIES;
            }

            $category = self::$randomAvailableCategories[rand(0, count(self::$randomAvailableCategories) - 1)];

            // Remove category from remaining available categories
            $randomOffset = array_search($category, self::$randomAvailableCategories);
            if (false !== $randomOffset && $randomOffset < count(self::$randomAvailableCategories)) {
                array_splice(self::$randomAvailableCategories, $randomOffset, 1);
            }
        }

        return new self($category);
    }

    private function getInflector(): Inflector
    {
        if (!self::$inflector) {
            self::$inflector = InflectorFactory::create()->build();
        }

        return self::$inflector;
    }

    private function __construct(string $category)
    {
        $this->category = $category;
        $this->categoryName = $this->getInflector()->capitalize($this->getInflector()->pluralize($category));
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    public function getCategoryValue(string $locale): string
    {
        if (!isset($this->localizedGenerators[$locale])) {
            $this->localizedGenerators[$locale] = FakerFactory::create($locale);
        }
        $localizedGenerator = $this->localizedGenerators[$locale];

        switch ($this->category) {
            case 'department':
                // Special case for department to increase the number of words since it can be specified
                return $localizedGenerator->department(6);
            default:
                // The category matched a faker property that generates random content
                return $localizedGenerator->{$this->category};
        }
    }
}
