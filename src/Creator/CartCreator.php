<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Address;
use Cart;
use Configuration;
use Context;
use Customer;
use Faker\Generator as Faker;

class CartCreator
{
    private Faker $faker;
    private EntityPicker $entityPicker;
    private CustomerCreator $customerCreator;
    private DateSpreader $dateSpreader;

    public function __construct(Faker $faker, EntityPicker $entityPicker, CustomerCreator $customerCreator, DateSpreader $dateSpreader)
    {
        $this->faker = $faker;
        $this->entityPicker = $entityPicker;
        $this->customerCreator = $customerCreator;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * Creates abandoned carts: optionally attached to existing customers, spread over time.
     *
     * @param array<int, array{id_product: int, id_product_attribute: int}> $products
     *
     * @return Cart[]
     */
    public function generate(int $number, array $products, bool $useExistingCustomers, int $maxProducts, int $spreadMonths, int $shopId): array
    {
        $customerIds = $useExistingCustomers ? $this->entityPicker->getCustomerIds() : [];
        $carts = [];
        for ($i = 0; $i < $number; ++$i) {
            $customer = null;
            $address = null;
            $customerId = $this->entityPicker->pickOne($customerIds);
            if (null !== $customerId) {
                $customer = new Customer($customerId);
                $address = $this->getOrCreateAddress($customer);
            }
            $carts[] = $this->createCart($products, $customer, $address, $maxProducts, null, $this->dateSpreader->randomPastDate($spreadMonths), $shopId);
        }

        return $carts;
    }

    /**
     * @param array<int, array{id_product: int, id_product_attribute: int}> $products
     */
    public function createCart(
        array $products,
        ?Customer $customer = null,
        ?Address $address = null,
        int $maxProducts = 5,
        ?int $carrierId = null,
        ?string $dateAdd = null,
        ?int $shopId = null
    ): Cart {
        $context = Context::getContext();
        $shopId = $shopId ?: (int) $context->shop->id;

        $cart = new Cart();
        $cart->id_shop = $shopId;
        $cart->id_shop_group = (int) $context->shop->id_shop_group;
        $cart->id_lang = (int) $context->language->id;
        $cart->id_currency = (int) ($context->currency->id ?? Configuration::get('PS_CURRENCY_DEFAULT'));
        $cart->id_customer = $customer ? (int) $customer->id : 0;
        $cart->id_guest = 0;
        $cart->secure_key = $customer ? $customer->secure_key : md5(uniqid());
        $cart->id_address_delivery = $address ? (int) $address->id : 0;
        $cart->id_address_invoice = $address ? (int) $address->id : 0;
        $cart->id_carrier = $carrierId ?: 0;
        if ($address && $carrierId) {
            $cart->delivery_option = json_encode([(int) $address->id => $carrierId . ',']);
        }
        $dateAdd = $dateAdd ?: date('Y-m-d H:i:s');
        $cart->date_add = $dateAdd;
        $cart->date_upd = $dateAdd;
        $cart->add(false);

        $this->fillCart($cart, $products, $maxProducts);

        return $cart;
    }

    /**
     * @param array<int, array{id_product: int, id_product_attribute: int}> $products
     *
     * @return int number of distinct products added
     */
    public function fillCart(Cart $cart, array $products, int $maxProducts): int
    {
        if (empty($products)) {
            return 0;
        }

        $target = $this->faker->numberBetween(1, max(1, $maxProducts));
        $added = 0;
        $attempts = 0;
        while ($added < $target && $attempts < $target * 3) {
            ++$attempts;
            $product = $products[array_rand($products)];
            $result = $cart->updateQty(
                $this->faker->numberBetween(1, 3),
                $product['id_product'],
                $product['id_product_attribute'] ?: null,
                false,
                'up',
                $cart->id_address_delivery ?: 0,
                null,
                false
            );
            if (true === $result) {
                ++$added;
            }
        }

        return $added;
    }

    public function getOrCreateAddress(Customer $customer): Address
    {
        $addressIds = $this->entityPicker->getAddressIdsForCustomer((int) $customer->id);
        $addressId = $this->entityPicker->pickOne($addressIds);
        if (null !== $addressId) {
            return new Address($addressId);
        }

        return $this->customerCreator->createAddress($customer);
    }
}
