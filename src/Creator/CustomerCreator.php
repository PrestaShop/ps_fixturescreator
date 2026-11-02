<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Address;
use Configuration;
use Country;
use Customer;
use Faker\Generator as Faker;
use State;

class CustomerCreator
{
    /** Hash of "prestashop" */
    private const PASSWORD_HASH = '$2y$10$WzLnGz9j..JtTFcjfjoWr.8L/rw39NwovNRwPxf6yk/AYWcIj/1Au';

    private Faker $faker;
    private EntityPicker $entityPicker;
    private DateSpreader $dateSpreader;

    /** @var array<int, int[]> country id => state ids */
    private array $statesByCountry = [];

    public function __construct(Faker $faker, EntityPicker $entityPicker, DateSpreader $dateSpreader)
    {
        $this->faker = $faker;
        $this->entityPicker = $entityPicker;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * @return Customer[]
     */
    public function generate(int $number, int $addressesPerCustomer = 0, bool $randomGroup = false, int $spreadMonths = 0): array
    {
        $customers = [];
        for ($i = 0; $i < $number; ++$i) {
            $customer = $this->createCustomer($randomGroup, $spreadMonths);
            for ($j = 0; $j < $addressesPerCustomer; ++$j) {
                $this->createAddress($customer, $j === 0 ? 'Home' : ucfirst($this->faker->word) . ' ' . ($j + 1));
            }
            $customers[] = $customer;
        }
        $this->entityPicker->invalidate('customer');

        return $customers;
    }

    public function createCustomer(bool $randomGroup = false, int $spreadMonths = 0): Customer
    {
        $customer = new Customer();
        $customer->firstname = $this->faker->firstName;
        $customer->lastname = $this->faker->lastName;
        $customer->email = $this->generateEmail($customer->firstname, $customer->lastname);
        $customer->passwd = self::PASSWORD_HASH;
        $customer->id_gender = $this->faker->numberBetween(1, 2);
        $customer->birthday = $this->faker->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d');
        $customer->newsletter = $this->faker->boolean(40);
        $customer->optin = $this->faker->boolean(20);
        $customer->active = $this->faker->boolean(97);
        $customer->is_guest = false;
        if ($this->faker->boolean(15)) {
            $customer->company = substr($this->faker->company, 0, 255);
        }

        $defaultGroupId = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $groupIds = [$defaultGroupId];
        if ($randomGroup) {
            $randomGroupId = $this->entityPicker->pickOne($this->entityPicker->getGroupIds());
            if (null !== $randomGroupId && $randomGroupId !== $defaultGroupId) {
                $groupIds[] = $randomGroupId;
                if ($this->faker->boolean(50)) {
                    $customer->id_default_group = $randomGroupId;
                }
            }
        }
        $customer->groupBox = $groupIds;

        $dateAdd = $this->dateSpreader->randomPastDate($spreadMonths);
        $customer->date_add = $dateAdd;
        $customer->date_upd = $dateAdd;
        $customer->add(false);

        return $customer;
    }

    public function createAddress(Customer $customer, string $alias = 'Home'): Address
    {
        $countryId = $this->pickCountryId();

        $address = new Address();
        $address->id_customer = (int) $customer->id;
        $address->id_country = $countryId;
        $address->id_state = $this->pickStateId($countryId);
        $address->alias = substr($alias, 0, 32);
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->company = $customer->company ?: '';
        $address->address1 = substr($this->faker->streetAddress, 0, 128);
        $address->address2 = $this->faker->boolean(20) ? substr($this->faker->secondaryAddress, 0, 128) : '';
        $address->city = substr($this->faker->city, 0, 64);
        $address->postcode = $this->faker->numerify('#####');
        $address->phone = $this->faker->numerify('0#########');
        $address->phone_mobile = $this->faker->boolean(50) ? $this->faker->numerify('06########') : '';
        $address->dni = $this->faker->numerify('########');
        $address->date_add = $customer->date_add;
        $address->date_upd = $customer->date_add;
        $address->add(false);

        return $address;
    }

    private function generateEmail(string $firstname, string $lastname): string
    {
        $local = strtolower(preg_replace('/[^a-z0-9]/i', '', $firstname . '.' . $lastname));

        return sprintf('%s.%s@%s', $local, substr(uniqid(), -6), $this->faker->safeEmailDomain);
    }

    private function pickCountryId(): int
    {
        $defaultCountryId = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        if ($this->faker->boolean(70)) {
            return $defaultCountryId;
        }
        $countryIds = $this->entityPicker->getCountryIds();

        return $this->entityPicker->pickOne($countryIds) ?? $defaultCountryId;
    }

    private function pickStateId(int $countryId): int
    {
        if (!isset($this->statesByCountry[$countryId])) {
            $this->statesByCountry[$countryId] = [];
            if (Country::containsStates($countryId)) {
                foreach (State::getStatesByIdCountry($countryId, true) as $state) {
                    $this->statesByCountry[$countryId][] = (int) $state['id_state'];
                }
            }
        }

        return $this->entityPicker->pickOne($this->statesByCountry[$countryId]) ?? 0;
    }
}
