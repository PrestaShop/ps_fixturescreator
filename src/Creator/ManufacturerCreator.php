<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;
use Manufacturer;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;

class ManufacturerCreator
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
     * @return int[] created manufacturer ids
     */
    public function generate(int $number, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $ids = [];
        for ($i = 0; $i < $number; ++$i) {
            $ids[] = (int) $this->createManufacturer($languages, $shopId)->id;
        }
        $this->entityPicker->invalidate('manufacturer');

        return $ids;
    }

    /**
     * @param Lang[] $languages
     */
    private function createManufacturer(array $languages, int $shopId): Manufacturer
    {
        $manufacturer = new Manufacturer();
        $manufacturer->name = substr($this->faker->company . ' ' . $this->faker->companySuffix, 0, 64);
        $manufacturer->active = $this->faker->boolean(95);
        $manufacturer->id_shop_list = [$shopId];

        $descriptions = [];
        $shortDescriptions = [];
        $metaTitles = [];
        $metaDescriptions = [];
        foreach ($languages as $lang) {
            $descriptions[$lang->getId()] = '<p>' . implode('</p><p>', $this->faker->paragraphs(2)) . '</p>';
            $shortDescriptions[$lang->getId()] = '<p>' . $this->faker->sentence(10) . '</p>';
            $metaTitles[$lang->getId()] = $manufacturer->name;
            $metaDescriptions[$lang->getId()] = $this->faker->sentence(8);
        }
        $manufacturer->description = $descriptions;
        $manufacturer->short_description = $shortDescriptions;
        $manufacturer->meta_title = $metaTitles;
        $manufacturer->meta_description = $metaDescriptions;

        $manufacturer->add();

        return $manufacturer;
    }
}
