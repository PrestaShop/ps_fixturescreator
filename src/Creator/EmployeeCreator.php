<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Employee;
use Faker\Generator as Faker;
use PrestaShop\PrestaShop\Core\Crypto\Hashing;

class EmployeeCreator
{
    private Faker $faker;
    private Hashing $hashing;
    private EntityPicker $entityPicker;

    public function __construct(Faker $faker, Hashing $hashing, EntityPicker $entityPicker)
    {
        $this->faker = $faker;
        $this->hashing = $hashing;
        $this->entityPicker = $entityPicker;
    }

    /**
     * @return int[] created employee ids
     */
    public function generate(int $number, int $langId, int $shopId): array
    {
        $profileIds = $this->entityPicker->getProfileIds();
        $passwordHash = $this->hashing->hash('prestashop_fixtures');
        $ids = [];

        for ($i = 0; $i < $number; ++$i) {
            $employee = new Employee();
            $employee->firstname = $this->faker->firstName;
            $employee->lastname = $this->faker->lastName;
            $employee->email = sprintf('employee.%s@%s', substr(uniqid(), -8), $this->faker->safeEmailDomain);
            $employee->passwd = $passwordHash;
            $employee->id_lang = $langId;
            $employee->id_profile = $this->entityPicker->pickOne($profileIds) ?? 1;
            $employee->active = $this->faker->boolean(90);
            $employee->bo_theme = 'default';
            $employee->bo_css = 'theme.css';
            $employee->default_tab = 1;
            $employee->bo_menu = true;
            $employee->bo_color = '';
            $employee->add();
            $employee->associateTo([$shopId]);
            $ids[] = (int) $employee->id;
        }
        $this->entityPicker->invalidate('employee');

        return $ids;
    }
}
