<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\Module\PsFixturesCreator\Command;

use Db;
use PrestaShop\Module\PsFixturesCreator\Creator\AttributeCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\CartCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\CartRuleCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\CustomerCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\CustomerThreadCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\FeatureCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\OrderCreator;
use Shop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command is used for appending the hook names in the configuration file.
 */
class ShopCreatorCommand extends Command
{
    private CustomerCreator $customerCreator;

    private CartCreator $cartCreator;

    private OrderCreator $orderCreator;

    private CartRuleCreator $cartRuleCreator;

    private AttributeCreator $attributeCreator;

    private FeatureCreator $featureCreator;

    private CustomerThreadCreator $customerThreadCreator;

    public function __construct(
        CustomerCreator $customerCreator,
        CartCreator $cartCreator,
        OrderCreator $orderCreator,
        CartRuleCreator $cartRuleCreator,
        AttributeCreator $attributeCreator,
        FeatureCreator $featureCreator,
        CustomerThreadCreator $customerThreadCreator
    ) {
        parent::__construct(null);

        $this->customerCreator = $customerCreator;
        $this->cartCreator = $cartCreator;
        $this->orderCreator = $orderCreator;
        $this->cartRuleCreator = $cartRuleCreator;
        $this->attributeCreator = $attributeCreator;
        $this->featureCreator = $featureCreator;
        $this->customerThreadCreator = $customerThreadCreator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('prestashop:shop-creator')
            ->addOption('orders', null, InputOption::VALUE_OPTIONAL, 'Number of orders to create', 0)
            ->addOption('customers', null, InputOption::VALUE_OPTIONAL, 'Number of customers without order to create', 0)
            ->addOption('carts', null, InputOption::VALUE_OPTIONAL, 'Number of carts to create', 0)
            ->addOption('cart-rules', null, InputOption::VALUE_OPTIONAL, 'Number of cart rules to create', 0)
            ->addOption('shopId', null, InputOption::VALUE_OPTIONAL, 'The shop identifier', 1)
            ->addOption('shopGroupId', null, InputOption::VALUE_OPTIONAL, 'The shop group identifier', 1)
            ->addOption('languageId', null, InputOption::VALUE_OPTIONAL, 'The languageId identifier', 1)
            ->addOption('attributeGroups', null, InputOption::VALUE_OPTIONAL, 'Number of attribute groups', 0)
            ->addOption('attributes', null, InputOption::VALUE_OPTIONAL, 'Number of attributes per attribute group', 10)
            ->addOption('features', null, InputOption::VALUE_OPTIONAL, 'Number of features', 0)
            ->addOption('featureValues', null, InputOption::VALUE_OPTIONAL, 'Number of values per feature', 10)
            ->addOption('threads', null, InputOption::VALUE_OPTIONAL, 'Number of threads to create', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \Context::getContext()->currency = \Currency::getDefaultCurrency();

        $numberOfOrders = (int) $input->getOption('orders');
        $numberOfCustomerWithoutOrder = (int) $input->getOption('customers');
        $numberOfCarts = (int) $input->getOption('carts');
        $numberOfCartRules = (int) $input->getOption('cart-rules');
        $idLang = (int) $input->getOption('languageId');
        $idshop = (int) $input->getOption('shopId');
        $idShopGroup = (int) $input->getOption('shopGroupId');
        $numberOfAttributeGroups = (int) $input->getOption('attributeGroups');
        $numberOfAttributes = (int) $input->getOption('attributes');
        $numberOfFeatures = (int) $input->getOption('features');
        $numberOfFeatureValues = (int) $input->getOption('featureValues');
        $numberOfThreads = (int) $input->getOption('threads');

        $productIds = $this->getStandardProducts($idLang);

        // Create customers (without order)
        if (!empty($numberOfCustomerWithoutOrder)) {
            $this->customerCreator->generate($numberOfCustomerWithoutOrder);
            $output->writeln(sprintf('%s customer(s) without orders created.', $numberOfCustomerWithoutOrder));
        }

        // create cart rules
        if (!empty($numberOfCartRules)) {
            $this->cartRuleCreator->generate($numberOfCartRules, $idLang);
            $output->writeln(sprintf('%s cart rule(s) created.', $numberOfCartRules));
        }

        // create features
        if ($numberOfFeatures > 0 && $numberOfFeatureValues > 0) {
            $this->featureCreator->generate($numberOfFeatures, $numberOfFeatureValues, $idshop);
            $output->writeln(sprintf('Created %s feature(s) with %s different values each.', $numberOfFeatures, $numberOfFeatureValues));
        }

        // Create attributes
        if ($numberOfAttributeGroups > 0 && $numberOfAttributes > 0) {
            $this->attributeCreator->generate($numberOfAttributeGroups, $numberOfAttributes, $idshop);
            $output->writeln(sprintf('Created %s attribute group(s) with %s different values each.', $numberOfAttributeGroups, $numberOfAttributes));
        }

        // Carts and orders are created last, so they can use new products randomly
        // Create carts
        if (!empty($numberOfCarts)) {
            $this->cartCreator->generate($numberOfCarts, $productIds);
            $output->writeln(sprintf('%s cart(s) created.', $numberOfCarts));
        }

        // Create orders
        if (!empty($numberOfOrders)) {
            $this->orderCreator->generate($numberOfOrders, $idShopGroup, $idshop, $productIds);
            $output->writeln(sprintf('%s order(s) created.', $numberOfOrders));
        }

        // Create customer threads
        if (!empty($numberOfThreads)) {
            $this->customerThreadCreator->generate($numberOfThreads, $idshop);
            $output->writeln(sprintf('%s threads(s) created', $numberOfThreads));
        }

        return 0;
    }

    /**
     * @param int $id_lang Language identifier
     *
     * @return bool|array
     */
    private function getStandardProducts($id_lang, bool $front = true)
    {
        $sql = 'SELECT p.`id_product`, pl.`name`
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                WHERE p.`product_type` = "standard" AND pl.`id_lang` = ' . (int) $id_lang . '
                ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                ORDER BY pl.`name`';

        return Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
