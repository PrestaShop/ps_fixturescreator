<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Configuration;
use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Store;

class StoreCreator
{
    private Faker $faker;
    private LangRepository $langRepository;

    public function __construct(Faker $faker, LangRepository $langRepository)
    {
        $this->faker = $faker;
        $this->langRepository = $langRepository;
    }

    /**
     * @return int[] created store ids
     */
    public function generate(int $number, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $defaultCountryId = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $hours = json_encode(array_fill(0, 7, ['09:00-12:00', '14:00-19:00']));
        $ids = [];

        for ($i = 0; $i < $number; ++$i) {
            $store = new Store();
            $store->id_country = $defaultCountryId;
            $store->id_state = 0;
            $store->city = substr($this->faker->city, 0, 64);
            $store->postcode = $this->faker->numerify('#####');
            $store->latitude = $this->faker->latitude(41, 51);
            $store->longitude = $this->faker->longitude(-5, 9);
            $store->phone = $this->faker->numerify('0#########');
            $store->email = $this->faker->safeEmail;
            $store->active = true;
            $store->id_shop_list = [$shopId];

            $names = [];
            $addresses = [];
            $notes = [];
            $hoursByLang = [];
            foreach ($languages as $lang) {
                $names[$lang->getId()] = substr($this->faker->company . ' ' . $store->city, 0, 255);
                $addresses[$lang->getId()] = substr($this->faker->streetAddress, 0, 255);
                $notes[$lang->getId()] = $this->faker->sentence(8);
                $hoursByLang[$lang->getId()] = $hours;
            }
            $store->name = $names;
            $store->address1 = $addresses;
            $store->note = $notes;
            $store->hours = $hoursByLang;
            $store->add();
            $ids[] = (int) $store->id;
        }

        return $ids;
    }
}
