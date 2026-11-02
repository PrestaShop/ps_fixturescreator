<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Supplier;

class SupplierCreator
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
     * @return int[] created supplier ids
     */
    public function generate(int $number, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $ids = [];
        for ($i = 0; $i < $number; ++$i) {
            $ids[] = (int) $this->createSupplier($languages, $shopId)->id;
        }
        $this->entityPicker->invalidate('supplier');

        return $ids;
    }

    /**
     * @param Lang[] $languages
     */
    private function createSupplier(array $languages, int $shopId): Supplier
    {
        $supplier = new Supplier();
        $supplier->name = substr($this->faker->company, 0, 64);
        $supplier->active = $this->faker->boolean(95);
        $supplier->id_shop_list = [$shopId];

        $descriptions = [];
        $metaTitles = [];
        $metaDescriptions = [];
        foreach ($languages as $lang) {
            $descriptions[$lang->getId()] = '<p>' . implode('</p><p>', $this->faker->paragraphs(2)) . '</p>';
            $metaTitles[$lang->getId()] = $supplier->name;
            $metaDescriptions[$lang->getId()] = $this->faker->sentence(8);
        }
        $supplier->description = $descriptions;
        $supplier->meta_title = $metaTitles;
        $supplier->meta_description = $metaDescriptions;

        $supplier->add();

        return $supplier;
    }
}
