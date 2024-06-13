<?php

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Cart;
use Faker\Generator as Faker;

class CartCreator
{
    protected Faker $faker;

    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    public function generate(int $number, array $productIds): void
    {
        for ($i = 0; $i < $number; ++$i) {
            $this->createCart($productIds);
        }
    }

    public function createCart(array $productIds): Cart
    {
        $cart = new Cart();
        $cart->id_currency = 1;
        $cart->add();

        for ($i = 0; $i < $this->faker->numberBetween(1, 5); ++$i) {
            $randomProduct = $this->faker->randomElement($productIds);
            $cart->updateQty($this->faker->numberBetween(1, 3), $randomProduct['id_product']);
        }

        return $cart;
    }
}
