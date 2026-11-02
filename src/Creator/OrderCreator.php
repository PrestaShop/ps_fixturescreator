<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Address;
use Carrier;
use Cart;
use Configuration;
use Context;
use Currency;
use Customer;
use Faker\Generator as Faker;
use Order;
use OrderCarrier;
use OrderDetail;
use OrderHistory;
use Tools;

/**
 * Creates orders the same way PaymentModule::validateOrder does, minus emails and hooks:
 * cart, order, order details, carrier, status history, payment and invoice.
 */
class OrderCreator
{
    /** @var array<int, array{weight: int, states: string[]}> */
    private const STATE_SCENARIOS = [
        ['weight' => 45, 'states' => ['PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_DELIVERED']],
        ['weight' => 15, 'states' => ['PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING']],
        ['weight' => 10, 'states' => ['PS_OS_PAYMENT', 'PS_OS_PREPARATION']],
        ['weight' => 8, 'states' => ['PS_OS_BANKWIRE']],
        ['weight' => 5, 'states' => ['PS_OS_CHEQUE']],
        ['weight' => 5, 'states' => ['PS_OS_PAYMENT', 'PS_OS_CANCELED']],
        ['weight' => 5, 'states' => ['PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_DELIVERED', 'PS_OS_REFUND']],
        ['weight' => 4, 'states' => ['PS_OS_ERROR']],
        ['weight' => 3, 'states' => ['PS_OS_COD_VALIDATION', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_DELIVERED']],
    ];

    /** @var array<int, array{module: string, name: string}> */
    private const PAYMENTS = [
        ['module' => 'ps_wirepayment', 'name' => 'Bank wire'],
        ['module' => 'ps_checkpayment', 'name' => 'Payment by check'],
        ['module' => 'ps_cashondelivery', 'name' => 'Cash on delivery'],
        ['module' => 'fakerpayment', 'name' => 'Faker Payment'],
    ];

    private Faker $faker;
    private CustomerCreator $customerCreator;
    private CartCreator $cartCreator;
    private EntityPicker $entityPicker;
    private DateSpreader $dateSpreader;

    public function __construct(
        Faker $faker,
        CustomerCreator $customerCreator,
        CartCreator $cartCreator,
        EntityPicker $entityPicker,
        DateSpreader $dateSpreader
    ) {
        $this->faker = $faker;
        $this->customerCreator = $customerCreator;
        $this->cartCreator = $cartCreator;
        $this->entityPicker = $entityPicker;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * @param array<int, array{id_product: int, id_product_attribute: int}> $products
     *
     * @return array<string, int> counters of created entities
     */
    public function generate(
        int $number,
        int $shopGroupId,
        int $shopId,
        array $products,
        bool $useExistingCustomers = false,
        int $maxProducts = 5,
        bool $withInvoice = true,
        int $spreadMonths = 12
    ): array {
        $counters = ['orders' => 0, 'order_details' => 0, 'order_histories' => 0, 'customers' => 0, 'addresses' => 0, 'skipped' => 0];
        $customerIds = $useExistingCustomers ? $this->entityPicker->getCustomerIds() : [];

        for ($i = 0; $i < $number; ++$i) {
            $customerId = $this->entityPicker->pickOne($customerIds);
            if (null !== $customerId) {
                $customer = new Customer($customerId);
            } else {
                $customer = $this->customerCreator->createCustomer(false, $spreadMonths);
                ++$counters['customers'];
            }

            $addressIds = $this->entityPicker->getAddressIdsForCustomer((int) $customer->id);
            $addressId = $this->entityPicker->pickOne($addressIds);
            if (null !== $addressId) {
                $address = new Address($addressId);
            } else {
                $address = $this->customerCreator->createAddress($customer);
                ++$counters['addresses'];
            }

            $dateAdd = $this->dateSpreader->randomPastDate($spreadMonths);
            if ($dateAdd < $customer->date_add) {
                $dateAdd = $customer->date_add;
            }

            $cart = $this->cartCreator->createCart($products, $customer, $address, $maxProducts, null, $dateAdd, $shopId);
            if (empty($cart->getProducts())) {
                ++$counters['skipped'];
                continue;
            }
            $carrierId = $this->pickCarrierId($address, $customer, $cart);
            $cart->id_carrier = $carrierId;
            $cart->delivery_option = json_encode([(int) $address->id => $carrierId . ',']);
            $cart->update();

            $created = $this->createOrder($cart, $address, $customer, $shopGroupId, $shopId, $carrierId, $dateAdd, $withInvoice);
            ++$counters['orders'];
            $counters['order_details'] += $created['order_details'];
            $counters['order_histories'] += $created['order_histories'];
        }

        if ($counters['customers'] > 0) {
            $this->entityPicker->invalidate('customer');
        }

        return $counters;
    }

    /**
     * @return array{order: Order, order_details: int, order_histories: int}
     */
    public function createOrder(
        Cart $cart,
        Address $address,
        Customer $customer,
        int $shopGroupId,
        int $shopId,
        int $carrierId,
        string $dateAdd,
        bool $withInvoice
    ): array {
        $context = Context::getContext();
        $previousCart = $context->cart;
        $previousCustomer = $context->customer;
        $context->cart = $cart;
        $context->customer = $customer;

        try {
            $computingPrecision = method_exists($context, 'getComputingPrecision') ? $context->getComputingPrecision() : 2;
            $carrier = new Carrier($carrierId, (int) $cart->id_lang);
            $currency = new Currency((int) $cart->id_currency);
            $payment = $this->faker->randomElement(self::PAYMENTS);
            $scenario = $this->pickScenario();
            $firstStateId = (int) Configuration::get($scenario[0]);

            $order = new Order();
            $order->product_list = $cart->getProducts();
            $order->id_shop_group = $shopGroupId;
            $order->id_shop = $shopId;
            $order->id_cart = (int) $cart->id;
            $order->id_customer = (int) $customer->id;
            $order->id_address_delivery = (int) $address->id;
            $order->id_address_invoice = (int) $address->id;
            $order->id_currency = (int) $cart->id_currency;
            $order->id_carrier = $carrierId;
            $order->id_lang = (int) $cart->id_lang;
            $order->reference = Order::generateReference();
            $order->secure_key = $customer->secure_key;
            $order->payment = $payment['name'];
            $order->module = $payment['module'];
            $order->recyclable = false;
            $order->gift = false;
            $order->gift_message = '';
            $order->mobile_theme = false;
            $order->conversion_rate = (float) $currency->conversion_rate ?: 1;
            $order->carrier_tax_rate = (float) $carrier->getTaxesRate($address);

            $order->total_products = (float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $carrierId);
            $order->total_products_wt = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $carrierId);
            $order->total_discounts_tax_excl = (float) abs($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $carrierId));
            $order->total_discounts_tax_incl = (float) abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $carrierId));
            $order->total_discounts = $order->total_discounts_tax_incl;
            $order->total_shipping_tax_excl = (float) $cart->getPackageShippingCost($carrierId, false, null, $order->product_list);
            $order->total_shipping_tax_incl = (float) $cart->getPackageShippingCost($carrierId, true, null, $order->product_list);
            $order->total_shipping = $order->total_shipping_tax_incl;
            $order->total_wrapping_tax_excl = 0;
            $order->total_wrapping_tax_incl = 0;
            $order->total_wrapping = 0;
            $order->total_paid_tax_excl = Tools::ps_round((float) $cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $carrierId), $computingPrecision);
            $order->total_paid_tax_incl = Tools::ps_round((float) $cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $carrierId), $computingPrecision);
            $order->total_paid = $order->total_paid_tax_incl;
            $order->total_paid_real = 0;
            $order->round_mode = (int) Configuration::get('PS_PRICE_ROUND_MODE');
            $order->round_type = (int) Configuration::get('PS_ROUND_TYPE');
            $order->invoice_date = '0000-00-00 00:00:00';
            $order->delivery_date = '0000-00-00 00:00:00';
            $order->date_add = $dateAdd;
            $order->date_upd = $dateAdd;
            $order->add(false);

            // Order lines (also decrements stock, like a real order)
            $orderDetail = new OrderDetail(null, null, $context);
            $orderDetail->createList($order, $cart, $firstStateId, $order->product_list, 0, true);
            $orderDetailsCount = count($order->product_list);

            // Carrier line
            $orderCarrier = new OrderCarrier();
            $orderCarrier->id_order = (int) $order->id;
            $orderCarrier->id_carrier = $carrierId;
            $orderCarrier->weight = (float) $order->getTotalWeight();
            $orderCarrier->shipping_cost_tax_excl = (float) $order->total_shipping_tax_excl;
            $orderCarrier->shipping_cost_tax_incl = (float) $order->total_shipping_tax_incl;
            $orderCarrier->date_add = $dateAdd;
            $orderCarrier->add(false);
            if (in_array('PS_OS_SHIPPING', $scenario, true) || in_array('PS_OS_DELIVERED', $scenario, true)) {
                $orderCarrier->tracking_number = strtoupper($this->faker->bothify('??########??'));
                $orderCarrier->update();
            }

            // Status history, payment and invoice
            $historyDate = $dateAdd;
            $paid = false;
            foreach ($scenario as $stateKey) {
                $stateId = (int) Configuration::get($stateKey);
                if (!$stateId) {
                    continue;
                }
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->id_employee = 0;
                $history->changeIdOrderState($stateId, $order, true);
                $history->date_add = $historyDate;
                $history->add(false);

                $state = new \OrderState($stateId);
                if ($state->paid && !$paid && $order->total_paid > 0) {
                    $order->addOrderPayment(
                        (string) $order->total_paid_tax_incl,
                        $payment['name'],
                        strtoupper($this->faker->bothify('TX-########')),
                        $currency,
                        $historyDate
                    );
                    $paid = true;
                }

                $historyDate = $this->dateSpreader->randomDateAfter($historyDate, 72);
            }

            if ($withInvoice && !$order->hasInvoice() && $paid) {
                $order->setInvoice(true);
            }

            return [
                'order' => $order,
                'order_details' => $orderDetailsCount,
                'order_histories' => count($scenario),
            ];
        } finally {
            $context->cart = $previousCart;
            $context->customer = $previousCustomer;
        }
    }

    /**
     * @return string[] list of PS_OS_* configuration keys
     */
    private function pickScenario(): array
    {
        $total = 0;
        foreach (self::STATE_SCENARIOS as $scenario) {
            $total += $scenario['weight'];
        }
        $roll = $this->faker->numberBetween(1, $total);
        foreach (self::STATE_SCENARIOS as $scenario) {
            $roll -= $scenario['weight'];
            if ($roll <= 0) {
                return $scenario['states'];
            }
        }

        return self::STATE_SCENARIOS[0]['states'];
    }

    private function pickCarrierId(Address $address, Customer $customer, Cart $cart): int
    {
        $zoneId = (int) Address::getZoneById((int) $address->id);
        $carriers = Carrier::getCarriersForOrder($zoneId, $customer->getGroups(), $cart);
        if (!empty($carriers)) {
            $carrier = $this->faker->randomElement($carriers);

            return (int) $carrier['id_carrier'];
        }

        $carrierIds = $this->entityPicker->getCarrierIds();

        return $this->entityPicker->pickOne($carrierIds) ?? (int) Configuration::get('PS_CARRIER_DEFAULT');
    }
}
