<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;
use SpecificPrice;

/**
 * Specific prices are one of the heaviest parts of PrestaShop price computation:
 * each product listing resolves them per product, group, country, currency, quantity and date.
 */
class SpecificPriceCreator
{
    public const TYPE_MIXED = 'mixed';
    public const TYPE_AMOUNT = 'amount';
    public const TYPE_PERCENTAGE = 'percentage';

    private Faker $faker;
    private EntityPicker $entityPicker;

    public function __construct(Faker $faker, EntityPicker $entityPicker)
    {
        $this->faker = $faker;
        $this->entityPicker = $entityPicker;
    }

    /**
     * Creates $number specific prices on random existing products.
     *
     * @return int number of specific prices actually created
     */
    public function generate(int $number, string $type, int $shopId): int
    {
        $productIds = $this->entityPicker->getProductIds($shopId);
        if (empty($productIds)) {
            return 0;
        }

        $created = 0;
        for ($i = 0; $i < $number; ++$i) {
            $productId = (int) $productIds[array_rand($productIds)];
            if ($this->createForProduct($productId, 1, $type, $shopId) > 0) {
                ++$created;
            }
        }

        return $created;
    }

    /**
     * @return int number of specific prices actually created (duplicates are skipped by PrestaShop)
     */
    public function createForProduct(int $productId, int $number, string $type, int $shopId): int
    {
        $groupIds = $this->entityPicker->getGroupIds();
        $countryIds = $this->entityPicker->getCountryIds();
        $created = 0;

        for ($i = 0; $i < $number; ++$i) {
            $specificPrice = new SpecificPrice();
            $specificPrice->id_product = $productId;
            $specificPrice->id_product_attribute = 0;
            $specificPrice->id_shop = $this->faker->boolean(70) ? $shopId : 0;
            $specificPrice->id_shop_group = 0;
            $specificPrice->id_cart = 0;
            $specificPrice->id_currency = 0;
            $specificPrice->id_country = $this->faker->boolean(20) && !empty($countryIds) ? (int) $countryIds[array_rand($countryIds)] : 0;
            $specificPrice->id_group = $this->faker->boolean(30) && !empty($groupIds) ? (int) $groupIds[array_rand($groupIds)] : 0;
            $specificPrice->id_customer = 0;
            $specificPrice->id_specific_price_rule = 0;
            $specificPrice->price = -1;
            $specificPrice->from_quantity = (int) $this->faker->randomElement([1, 1, 1, 2, 3, 5, 10, 25]);

            $reductionType = $type === self::TYPE_MIXED ? $this->faker->randomElement([self::TYPE_AMOUNT, self::TYPE_PERCENTAGE]) : $type;
            $specificPrice->reduction_type = $reductionType;
            $specificPrice->reduction_tax = 1;
            $specificPrice->reduction = $reductionType === self::TYPE_PERCENTAGE
                ? $this->faker->numberBetween(5, 60) / 100
                : $this->faker->randomFloat(2, 1, 50);

            if ($this->faker->boolean(50)) {
                $from = $this->faker->dateTimeBetween('-6 months', '+1 month');
                $to = (clone $from)->modify(sprintf('+%d days', $this->faker->numberBetween(7, 180)));
                $specificPrice->from = $from->format('Y-m-d H:i:s');
                $specificPrice->to = $to->format('Y-m-d H:i:s');
            } else {
                $specificPrice->from = '0000-00-00 00:00:00';
                $specificPrice->to = '0000-00-00 00:00:00';
            }

            try {
                if ($specificPrice->add()) {
                    ++$created;
                }
            } catch (\Throwable $e) {
                // Duplicate rule (same product/group/country/quantity/dates): skip it
            }
        }

        return $created;
    }
}
