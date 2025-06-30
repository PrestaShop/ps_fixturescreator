<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Carrier;
use Faker\Generator as Faker;

class CarrierCreator
{
    private Faker $faker;

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
        $carrier->range_behavior = 0;
        $carrier->is_free = (bool) $this->faker->boolean(20); // 20% chance of being free
        $carrier->shipping_handling = (bool) $this->faker->boolean(50);
        $carrier->range_by_price = true;
        $carrier->range_by_weight = true;
        $carrier->grade = $this->faker->numberBetween(0, 9);
        $carrier->url = 'http://www.example.com/tracking?id=@';
        $carrier->max_width = 0;
        $carrier->max_height = 0;
        $carrier->max_depth = 0;
        $carrier->max_weight = 0;
        $carrier->position = 0;

        // Multilingual fields
        $delay = [];
        foreach (\Language::getLanguages(false) as $lang) {
            $delay[$lang['id_lang']] = $this->faker->sentence(3, false);
        }
        $carrier->delay = $delay;

        $carrier->add();

        // Add zones (example: Europe zone)
        $carrier->addZone(1);

        // Add groups (example: customer group)
        $carrier->addGroup(1);

        // Add shops
        $carrier->addShop(\Context::getContext()->shop->id);

        return $carrier;
    }
}