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

use PrestaShop\Module\PsFixturesCreator\Creator\CustomerThreadCreator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CustomerThreadCreatorCommand extends Command
{
    private CustomerThreadCreator $customerThreadCreator;

    public function __construct(
        CustomerThreadCreator $customerThreadCreator
    ) {
        parent::__construct(null);

        $this->customerThreadCreator = $customerThreadCreator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('prestashop:customer-thread')
            ->addOption('threads', null, InputOption::VALUE_OPTIONAL, 'Number of threads to create', 0)
            ->addOption('shopId', null, InputOption::VALUE_OPTIONAL, 'The shop identifier', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $numberOfThreads = (int) $input->getOption('threads');
        $shopId = (int) $input->getOption('shopId');

        // create threads
        if (!empty($numberOfThreads)) {
            $this->customerThreadCreator->generate($numberOfThreads, $shopId);
            $output->writeln(sprintf('%s threads(s) created', $numberOfThreads));
        }

        return 0;
    }
}
