<?php

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use DateTime;
use Employee;
use PrestaShop\PrestaShop\Adapter\StockManager;
use PrestaShopBundle\Entity\Repository\StockMovementRepository;
use PrestaShopBundle\Entity\StockMvt;
use Product;
use StockAvailable;

class StockMovementCreator
{
    protected StockManager $stockManager;
    protected StockMovementRepository $stockMvtRepository;
    protected Employee $employee;

    public function __construct(
      StockManager $stockManager,
      StockMovementRepository $stockMvtRepository
    ) {
        $this->stockManager = $stockManager;
        $this->stockMvtRepository = $stockMvtRepository;
        $this->employee = new Employee(1);
    }

    public function generate(int $number, int $productId): void
    {
        // Start
        $qtyProduct = 500;

        StockAvailable::setQuantity($productId, 0, $qtyProduct, null, false);

        for ($i = 0; $i < $number; ++$i) {
            $deltaQuantity = rand(-10, 10);

            $qtyProduct += $deltaQuantity;
            $this->createStockMovement($productId, $deltaQuantity);
        }

        StockAvailable::setQuantity($productId, 0, $qtyProduct, null, false);
    }

    public function createStockMovement(int $productId, int $deltaQuantity): void
    {
        $stockAvailable = $this->stockManager->getStockAvailableByProduct(new Product($productId));

        $stockMvt = new StockMvt();
        $stockMvt->setIdStock((int) $stockAvailable->id);
        $stockMvt->setIdEmployee($this->employee->id);
        $stockMvt->setEmployeeFirstname($this->employee->firstname);
        $stockMvt->setEmployeeLastname($this->employee->lastname);
        $stockMvt->setSign($deltaQuantity >= 1 ? 1 : -1);
        $stockMvt->setPhysicalQuantity(abs($deltaQuantity));
        $stockMvt->setDateAdd(new DateTime());

        $this->stockMvtRepository->saveStockMvt($stockMvt);
    }
}
