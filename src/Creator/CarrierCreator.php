<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Carrier;
use Db;
use Faker\Generator as Faker;
use Group;
use RangePrice;
use RangeWeight;
use Zone;

/**
 * Class CarrierCreator
 *
 */
class CarrierCreator
{
    protected Faker $faker;

    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    public function generate(int $number, int $idLang): array
    {
        $generatedCarriers = [];
        for ($i = 0; $i < $number; ++$i) {
            $generatedCarriers[] = $this->createCarrier($idLang, $i);
        }

        return $generatedCarriers;
    }

    private function createCarrier(int $idLang, int $carrierNumber): Carrier
    {
        $carrier = new Carrier();
        $carrier->name = 'Fake Carrier ' . ($carrierNumber + 1);
        $carrier->active = true;
        $carrier->deleted = false;
        $carrier->is_module = false;
        $carrier->shipping_external = false;
        $carrier->range_behavior = false;
        $carrier->is_free = (bool) $this->faker->boolean(20);
        $carrier->shipping_handling = (bool) $this->faker->boolean(50);
        /** @phpstan-ignore-next-line */
        $carrier->range_by_price = true;
        /** @phpstan-ignore-next-line */
        $carrier->range_by_weight = true;
        $carrier->grade = $this->faker->numberBetween(0, 9);
        $carrier->url = 'http://www.example.com/tracking?id=@';
        $carrier->max_width = 0;
        $carrier->max_height = 0;
        $carrier->max_depth = 0;
        $carrier->max_weight = 0;
        $carrier->position = 0;

        $delay = [];
        foreach (\Language::getLanguages(false) as $lang) {
            $delay[$lang['id_lang']] = $this->faker->sentence(3, false);
        }
        $carrier->delay = $delay;

        $carrier->add(); // Important: Save the carrier first to get its ID

        $idCarrier = (int) $carrier->id;

        // --- Handle Associations (Zones, Groups) via direct DB insert ---

        // Get existing active zones
        $zones = Zone::getZones(true);
        $zoneIds = [];
        if (!empty($zones)) {
            foreach ($zones as $zone) {
                $zoneIds[] = (int) $zone['id_zone'];
            }
        } else {
            // Fallback if no zones exist, ensure at least zone ID 1 is considered
            $zoneIds = [1];
            // You might want to create a default zone if it truly doesn't exist.
            // For now, we assume zone 1 is always present on a default PS install.
        }

        foreach ($zoneIds as $idZone) {
            Db::getInstance()->insert('carrier_zone', [
                'id_carrier' => $idCarrier,
                'id_zone' => $idZone,
            ]);
        }

        // Get existing active groups
        $groups = Group::getGroups(true);
        $groupIds = [];
        if (!empty($groups)) {
            foreach ($groups as $group) {
                $groupIds[] = (int) $group['id_group'];
            }
        } else {
            // Fallback if no groups exist, ensure at least group ID 1 is considered
            $groupIds = [1];
            // Similar assumption for group 1 (default Customer group).
        }

        foreach ($groupIds as $idGroup) {
            Db::getInstance()->insert('carrier_group', [
                'id_carrier' => $idCarrier,
                'id_group' => $idGroup,
            ]);
        }

        // --- Handle Ranges (Price, Weight) ---
        // A carrier needs at least one range to be valid for shipping calculation.
        // We create a generic range for price and weight.

        $idShop = (int) \Context::getContext()->shop->id;
        $idCurrency = (int) \Currency::getDefaultCurrency()->id;

        // Create a default price range
        $rangePrice = new RangePrice();
        $rangePrice->id_carrier = $idCarrier;
        $rangePrice->delimiter1 = 0.00;
        $rangePrice->delimiter2 = 1000000.00; // Large range to cover most cases
        $rangePrice->add();
        $idRangePrice = (int) $rangePrice->id;

        // Create a default weight range
        $rangeWeight = new RangeWeight();
        $rangeWeight->id_carrier = $idCarrier;
        $rangeWeight->delimiter1 = 0.00;
        $rangeWeight->delimiter2 = 1000.00; // Large range
        $rangeWeight->add();
        $idRangeWeight = (int) $rangeWeight->id;

        // --- Handle Delivery Costs ---
        // A carrier needs delivery costs defined per zone and range for each shop.
        // We'll add a flat cost for our created ranges and zones.

        foreach ($zoneIds as $idZone) {
            // Price based delivery cost
            Db::getInstance()->insert('delivery', [
                'id_carrier' => $idCarrier,
                'id_range_price' => $idRangePrice,
                'id_range_weight' => null, // This range is price-based
                'id_zone' => $idZone,
                'id_shop' => $idShop,
                'id_shop_group' => null, // Not associating with shop group here
                'price' => $this->faker->randomFloat(2, 2, 15), // Random shipping cost
            ]);

            // Weight based delivery cost
            Db::getInstance()->insert('delivery', [
                'id_carrier' => $idCarrier,
                'id_range_price' => null, // This range is weight-based
                'id_range_weight' => $idRangeWeight,
                'id_zone' => $idZone,
                'id_shop' => $idShop,
                'id_shop_group' => null,
                'price' => $this->faker->randomFloat(2, 2, 15), // Random shipping cost
            ]);
        }

        // --- No need for $carrier->update() after direct DB inserts for associations ---
        // The associations are handled directly by SQL INSERTs.

        return $carrier;
    }
}
