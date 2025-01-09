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

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;

abstract class AbstractProductCreator
{
    protected FeatureCreator $featureCreator;
    protected StockMovementCreator $stockMovementCreator;
    protected Connection $connection;
    protected string $dbPrefix;

    public function __construct(
        FeatureCreator $featureCreator,
        StockMovementCreator $stockMovementCreator,
        Connection $connection,
        string $dbPrefix
    ) {
        $this->featureCreator = $featureCreator;
        $this->stockMovementCreator = $stockMovementCreator;
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    protected function associateFeatures(int $productId, int $numberOfFeatures, int $numberOfFeatureValues, int $shopId): void
    {
        $featureIds = $this->getFeatureWithAtLeast($numberOfFeatureValues);
        // Create the missing attribute groups if needed
        if (count($featureIds) < $numberOfFeatures) {
            $generatedFeatures = $this->featureCreator->generate($numberOfFeatures - count($featureIds), $numberOfFeatureValues, $shopId);
            foreach ($generatedFeatures as $feature) {
                $featureIds[] = (int) $feature->id;
            }
        }

        foreach ($featureIds as $featureId) {
            $randomFeatureValueIds = $this->getRandomValues($featureId, $numberOfFeatureValues);
            $this->associateFeatureValues($productId, $featureId, $randomFeatureValueIds);
        }
    }

    protected function associateFeatureValues(int $productId, int $featureId, array $featureValueIds): void
    {
        foreach ($featureValueIds as $featureValueId) {
            $insertedValues = [
                'id_product' => $productId,
                'id_feature' => $featureId,
                'id_feature_value' => $featureValueId,
            ];
            $this->connection->insert($this->dbPrefix . 'feature_product', $insertedValues);
        }
    }

    protected function associateStockMovements(int $productId, int $numberOfStockMovements): void
    {
        if ($numberOfStockMovements <= 0) {
            return;
        }

        $this->stockMovementCreator->generate($numberOfStockMovements, $productId);
    }

    protected function getRandomValues(int $featureId, int $numberOfFeatureValues): array
    {
        $featureValueIds = $this->connection->createQueryBuilder()
            ->select('fv.id_feature_value')
            ->from($this->dbPrefix . 'feature_value', 'fv')
            ->where('fv.id_feature = :featureId')
            ->setParameter('featureId', $featureId)
            ->execute()
            ->fetchAllAssociative()
        ;

        $randomKeys = array_rand($featureValueIds, $numberOfFeatureValues);
        $randomFeatureValueIds = [];
        foreach ($randomKeys as $randomKey) {
            $randomFeatureValueIds[] = (int) $featureValueIds[$randomKey]['id_feature_value'];
        }

        return $randomFeatureValueIds;
    }

    protected function getFeatureWithAtLeast(int $minimumValuesNumber): array
    {
        $features = $this->connection->createQueryBuilder()
            ->select('f.id_feature, COUNT(fv.id_feature_value) AS featureValuesNb')
            ->from($this->dbPrefix . 'feature', 'f')
            ->leftJoin(
                'f',
                $this->dbPrefix . 'feature_value',
                'fv',
                'f.id_feature = fv.id_feature'
            )
            ->addGroupBy('f.id_feature')
            ->execute()
            ->fetchAllAssociative()
        ;

        $featureIds = array_map(static function (array $feature) {
            return (int) $feature['id_feature'];
        }, array_filter($features, static function (array $feature) use ($minimumValuesNumber) {
            return $feature['featureValuesNb'] >= $minimumValuesNumber;
        }));

        return array_values($featureIds);
    }
}
