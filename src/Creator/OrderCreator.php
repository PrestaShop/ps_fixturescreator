<?php

namespace PrestaShop\Module\ps_fixturescreator\Creator;

use Address;
use Cart;
use Context;
use Customer;
use Employee;
use Faker\Generator as Faker;
use Order;
use OrderState;

class OrderCreator
{
    private Faker $faker;

    private CartCreator $cartCreator;

    private CustomerCreator $customerCreator;

    public function __construct(
        Faker $faker,
        CartCreator $cartCreator,
        CustomerCreator $customerCreator
    ) {
        $this->faker = $faker;
        $this->cartCreator = $cartCreator;
        $this->customerCreator = $customerCreator;

        // Because we could be in CLI mode, there might be no employee in context, so we must set it manually
        $context = Context::getContext();
        if (!isset($context->employee) || !isset($context->employee->id)) {
            $context->employee = new Employee(1);
        }
    }

    public function generate(int $number, int $idShopGroup, int $idShop, array $productIds): void
    {
        for ($i = 0; $i < $number; ++$i) {
            $customer = $this->customerCreator->createCustomer();
            $address = $this->createAddress($customer);
            $cart = $this->cartCreator->createCart($productIds);
            $this->createOrder($cart, $address, $customer, $idShopGroup, $idShop);
        }
    }

    private function createAddress(Customer $customer): Address
    {
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->alias = 'default';
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->address1 = $this->faker->streetAddress;
        $address->city = $this->faker->city;
        $address->postcode = $this->faker->postcode;
        $address->id_country = $this->faker->numberBetween(1, 1);
        $address->phone = $this->faker->phoneNumber;
        $address->dni = '1234567891012131';
        $address->add();

        return $address;
    }

    private function createOrder(
        Cart $cart,
        Address $address,
        Customer $customer,
        int $idShopGroup,
        int $idShop
    ): void {
        $order = new Order();
        $order->id_shop_group = $idShopGroup;
        $order->id_shop = $idShop;
        $order->id_cart = $cart->id;
        $order->id_customer = $customer->id;
        $order->id_address_delivery = $address->id;
        $order->id_address_invoice = $address->id;
        $order->id_currency = $cart->id_currency;
        $order->id_carrier = 1;
        $order->id_lang = $cart->id_lang;
        $order->payment = 'Faker Payment';
        $order->module = 'fakerpayment';
        $order->total_paid = $cart->getOrderTotal(true, Cart::BOTH);
        $order->total_paid_real = $cart->getOrderTotal(true, Cart::BOTH);
        $order->total_paid_tax_incl = $cart->getOrderTotal(true, Cart::BOTH);
        $order->total_paid_tax_excl = $cart->getOrderTotal(false, Cart::BOTH);
        $order->total_products = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $order->total_products_wt = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        $order->total_shipping = 0;
        $order->secure_key = md5('test');
        $order->carrier_tax_rate = 0;
        $order->conversion_rate = 1;

        $order->add();

        $orderStatus = new OrderState(3); // 3 est l'ID du statut de la commande "en cours de préparation"
        $order->setCurrentState($orderStatus->id);
        $order->save();
    }
}
