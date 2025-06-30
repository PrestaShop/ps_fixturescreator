<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Command;

use PrestaShop\Module\PsFixturesCreator\Creator\CarrierCreator; // Importez le CarrierCreator
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PrestaShop\PrestaShop\Adapter\LegacyContextLoader; // Nécessaire pour charger le contexte de PrestaShop

class CarrierCreatorCommand extends Command
{
    private CarrierCreator $carrierCreator;
    private LegacyContextLoader $legacyContextLoader;

    public function __construct(
        CarrierCreator $carrierCreator,
        LegacyContextLoader $legacyContextLoader
    ) {
        parent::__construct(null);
        $this->carrierCreator = $carrierCreator;
        $this->legacyContextLoader = $legacyContextLoader;
    }

    protected function configure(): void
    {
        $this
            ->setName('prestashop:carrier-creator') // Nom de la commande
            ->setDescription('Creates a given number of carriers') // Description de la commande
            ->addOption('carriers', null, InputOption::VALUE_OPTIONAL, 'Number of carriers to create', 0)
            ->addOption('shopId', null, InputOption::VALUE_OPTIONAL, 'The shop identifier', 1)
            ->addOption('languageId', null, InputOption::VALUE_OPTIONAL, 'The language identifier', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->legacyContextLoader->loadGenericContext( // Charge le contexte pour les opérations PrestaShop
            null,
            \Currency::getDefaultCurrency()->id,
            1
        );

        $numberOfCarriers = (int) $input->getOption('carriers');
        $shopId = (int) $input->getOption('shopId');
        $idLang = (int) $input->getOption('languageId');

        if ($numberOfCarriers > 0) {
            $this->carrierCreator->generate($numberOfCarriers, $idLang);
            $output->writeln(sprintf('%s carrier(s) created.', $numberOfCarriers));
        } else {
            $output->writeln('No carriers to create. Use --carriers option to specify the number of carriers.');
        }

        return 0;
    }
}