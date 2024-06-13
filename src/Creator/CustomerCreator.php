<?php

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Customer;
use Faker\Generator as Faker;

class CustomerCreator
{
    protected Faker $faker;

    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    public function generate(int $number): void
    {
        for ($i = 0; $i < $number; ++$i) {
            $this->createCustomer();
        }
    }

    public function createCustomer(): Customer
    {
        $customer = new Customer();
        $customer->firstname = $this->faker->firstName;
        $customer->lastname = $this->faker->lastName;
        $customer->email = $this->faker->email;
        $customer->passwd = '$2y$10$WzLnGz9j..JtTFcjfjoWr.8L/rw39NwovNRwPxf6yk/AYWcIj/1Au';
        $customer->add();

        return $customer;
    }
}
