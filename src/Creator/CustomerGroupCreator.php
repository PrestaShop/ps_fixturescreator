<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;
use Group;
use PrestaShopBundle\Entity\Repository\LangRepository;

class CustomerGroupCreator
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
     * @return int[] created group ids
     */
    public function generate(int $number, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $existing = count($this->entityPicker->getGroupIds());
        $ids = [];
        for ($i = 1; $i <= $number; ++$i) {
            $group = new Group();
            $group->reduction = $this->faker->boolean(40) ? (string) $this->faker->numberBetween(1, 30) : '0';
            $group->price_display_method = $this->faker->numberBetween(0, 1);
            $group->show_prices = true;
            $group->id_shop_list = [$shopId];
            $names = [];
            foreach ($languages as $lang) {
                $names[$lang->getId()] = substr(sprintf('%s %d %s', ucfirst($this->faker->word), $existing + $i, $lang->getLocale()), 0, 32);
            }
            $group->name = $names;
            $group->add();
            $ids[] = (int) $group->id;
        }
        $this->entityPicker->invalidate('group');

        return $ids;
    }
}
