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

use PrestaShop\Module\PsFixturesCreator\Creator\ProductCombinationCreator;
use PrestaShop\Module\PsFixturesCreator\Creator\ProductCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command is used for appending the hook names in the configuration file.
 */
class ProductCreatorCommand extends Command
{
    private ProductCreator $productCreator;

    private ProductCombinationCreator $productCombinationCreator;

    public function __construct(
        ProductCreator $productCreator,
        ProductCombinationCreator $productCombinationCreator
    ) {
        parent::__construct(null);

        $this->productCreator = $productCreator;
        $this->productCombinationCreator = $productCombinationCreator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('prestashop:product-creator')
            ->addOption('products', null, InputOption::VALUE_OPTIONAL, 'Number of products to create', 0)
            ->addOption('productsWithCombinations', null, InputOption::VALUE_OPTIONAL, 'Number of products with combinations to create', 0)
            ->addOption('shopId', null, InputOption::VALUE_OPTIONAL, 'The shop identifier', 1)
            ->addOption('shopGroupId', null, InputOption::VALUE_OPTIONAL, 'The shop group identifier', 1)
            ->addOption('attributeGroups', null, InputOption::VALUE_OPTIONAL, 'Number of attribute groups per product', 2)
            ->addOption('attributes', null, InputOption::VALUE_OPTIONAL, 'Number of attributes per attribute group', 5)
            ->addOption('features', null, InputOption::VALUE_OPTIONAL, 'Number of features per product', 2)
            ->addOption('featureValues', null, InputOption::VALUE_OPTIONAL, 'Number of values per feature', 5)
            ->addOption('stockMovements', null, InputOption::VALUE_OPTIONAL, 'Number of stock movements per product', 0)
            ->addOption('images', null, InputOption::VALUE_OPTIONAL, 'Number of images per product', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \Context::getContext()->currency = \Currency::getDefaultCurrency();

        $numberOfProducts = (int) $input->getOption('products');
        $shopId = (int) $input->getOption('shopId');
        $numberOfAttributeGroups = (int) $input->getOption('attributeGroups');
        $numberOfAttributes = (int) $input->getOption('attributes');
        $numberOfFeatures = (int) $input->getOption('features');
        $numberOfFeatureValues = (int) $input->getOption('featureValues');
        $numberOfStockMovements = (int) $input->getOption('stockMovements');
        $numberOfImages = (int) $input->getOption('images');
        $productsWithCombinations = (int) $input->getOption('productsWithCombinations');

        // create products
        if (!empty($numberOfProducts)) {
            $this->productCreator->generate(
                $numberOfProducts,
                $numberOfFeatures,
                $numberOfFeatureValues,
                $numberOfStockMovements,
                $numberOfImages,
                $shopId
            );
            $output->writeln(sprintf('%s product(s) created', $numberOfProducts));
        }

        // create product with combinations, if attributes are needed they will be created dynamically
        if (!empty($productsWithCombinations)) {
            $this->productCombinationCreator->generate(
                $productsWithCombinations,
                $numberOfAttributeGroups,
                $numberOfAttributes,
                $numberOfFeatures,
                $numberOfFeatureValues,
                $numberOfImages,
                $shopId
            );
            $output->writeln(sprintf('%s product(s) with combinations created', $productsWithCombinations));
        }

        return 0;
    }
}
