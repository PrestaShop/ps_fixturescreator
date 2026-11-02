<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Faker\Generator as Faker;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\CombinationId;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Entity\Attribute;
use PrestaShopBundle\Entity\AttributeGroup;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Product;

class ProductCombinationCreator extends AbstractProductCreator
{
    private EntityManagerInterface $entityManager;

    private CommandBusInterface $commandBus;

    private AttributeCreator $attributeCreator;

    private LangRepository $langRepository;

    private EntityPicker $entityPicker;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommandBusInterface $commandBus,
        AttributeCreator $attributeCreator,
        LangRepository $langRepository,
        FeatureCreator $featureCreator,
        Connection $connection,
        string $dbPrefix,
        Faker $faker,
        StockMovementCreator $stockMovementCreator,
        ProductImageCreator $productImageCreator,
        ProductEnricher $productEnricher,
        EntityPicker $entityPicker
    ) {
        parent::__construct($featureCreator, $productImageCreator, $stockMovementCreator, $productEnricher, $connection, $faker, $dbPrefix);
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->attributeCreator = $attributeCreator;
        $this->langRepository = $langRepository;
        $this->entityPicker = $entityPicker;
    }

    public static function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'attribute_groups' => 2,
            'attributes' => 5,
        ]);
    }

    /**
     * Legacy signature kept for the prestashop:product-creator command.
     */
    public function generate(
        int $productWithCombinations,
        int $attributeGroupNumber,
        int $attributeValuePerGroupNumber,
        int $numberOfFeatures,
        int $numberOfFeatureValues,
        int $numberOfImages,
        int $numberOfStockMovements,
        int $shopId
    ): void {
        $this->generateWithOptions($productWithCombinations, [
            'attribute_groups' => $attributeGroupNumber,
            'attributes' => $attributeValuePerGroupNumber,
            'features' => $numberOfFeatures,
            'feature_values' => $numberOfFeatureValues,
            'images' => $numberOfImages,
            'stock_movements' => $numberOfStockMovements,
        ], $shopId);
    }

    /**
     * @param array<string, mixed> $options see getDefaultOptions()
     *
     * @return array<string, int> counters
     */
    public function generateWithOptions(int $number, array $options, int $shopId): array
    {
        $options = array_merge(static::getDefaultOptions(), $options);
        $attributeGroupNumber = max(1, (int) $options['attribute_groups']);
        $attributeValuePerGroupNumber = max(1, (int) $options['attributes']);
        $counters = ['products' => 0, 'combinations' => 0, 'images' => 0, 'stock_movements' => 0, 'feature_links' => 0];

        $attributeGroups = $this->getAttributeGroupWithAtLeast($attributeValuePerGroupNumber);
        // Create the missing attribute groups if needed
        if (count($attributeGroups) < $attributeGroupNumber) {
            $attributeGroups = array_merge(
                $attributeGroups,
                $this->attributeCreator->generate($attributeGroupNumber - count($attributeGroups), $attributeValuePerGroupNumber, $shopId)
            );
        }

        $languages = $this->langRepository->findAll();

        for ($i = 1; $i <= $number; ++$i) {
            $combinationAttributes = $this->getAttributesForGeneration($attributeGroups, $attributeGroupNumber, $attributeValuePerGroupNumber);
            $productName = $this->faker->productName;
            $productNames = [];
            /** @var Lang $lang */
            foreach ($languages as $lang) {
                $productNames[$lang->getId()] = $productName . ' ' . $lang->getLocale();
            }

            $productId = $this->createProduct($productNames, $shopId);
            $combinationsIds = $this->generateCombinations($productId, $combinationAttributes, $shopId);
            ++$counters['products'];
            $counters['combinations'] += count($combinationsIds);

            // The CQRS command creates a bare, inactive product: give it real data
            $product = new Product($productId, false, null, $shopId);
            $this->productEnricher->fillBaseFields($product, $productNames, $shopId);
            $product->update();
            $this->productEnricher->randomizeCombinations($productId, $combinationsIds, $shopId);

            $decorated = $this->decorateProduct($productId, $combinationsIds, $options, $shopId);
            foreach ($decorated as $key => $value) {
                $counters[$key] += $value;
            }
        }
        $this->entityPicker->invalidate('product');

        return $counters;
    }

    /**
     * @param array<int, string> $productNames
     */
    private function createProduct(array $productNames, int $shopId): int
    {
        if (version_compare(_PS_VERSION_, '8.1', '>=')) {
            /** @var ProductId $newProductId */
            $newProductId = $this->commandBus->handle(new AddProductCommand(
                ProductType::TYPE_COMBINATIONS,
                $shopId,
                $productNames
            ));
        } elseif (version_compare(_PS_VERSION_, '8.0', '>=')) {
            /** @var ProductId $newProductId */
            $newProductId = $this->commandBus->handle(new AddProductCommand(
                ProductType::TYPE_COMBINATIONS,
                $productNames
            ));
        } else {
            throw new \RuntimeException(sprintf('Version %s not handled to generate combinations', _PS_VERSION_));
        }

        return $newProductId->getValue();
    }

    /**
     * @param array<int, int[]> $combinationAttributes
     *
     * @return int[]
     */
    private function generateCombinations(int $productId, array $combinationAttributes, int $shopId): array
    {
        if (version_compare(_PS_VERSION_, '8.1', '>=')) {
            $combinationsIds = $this->commandBus->handle(new GenerateProductCombinationsCommand(
                $productId,
                $combinationAttributes,
                $shopId ? ShopConstraint::shop($shopId) : ShopConstraint::allShops()
            ));
        } else {
            $combinationsIds = $this->commandBus->handle(new GenerateProductCombinationsCommand(
                $productId,
                $combinationAttributes
            ));
        }

        return array_map(static function (CombinationId $combination) {
            return $combination->getValue();
        }, $combinationsIds);
    }

    /**
     * @param AttributeGroup[] $attributeGroups
     * @param int $attributeGroupNumber
     * @param int $attributeValuePerGroupNumber
     *
     * @return array
     */
    private function getAttributesForGeneration(array $attributeGroups, int $attributeGroupNumber, int $attributeValuePerGroupNumber): array
    {
        $attributeIdsByGroup = [];
        $randomAttributeGroupsKeys = (array) array_rand($attributeGroups, min($attributeGroupNumber, count($attributeGroups)));
        foreach ($randomAttributeGroupsKeys as $randomAttributeGroupKey) {
            $attributeGroup = $attributeGroups[$randomAttributeGroupKey];
            $attributeIdsByGroup[$attributeGroup->getId()] = [];

            /** @var Attribute[] $attributes */
            $attributes = $attributeGroup->getAttributes()->toArray();
            $randomAttributeKeys = (array) array_rand($attributes, min($attributeValuePerGroupNumber, count($attributes)));
            foreach ($randomAttributeKeys as $randomAttributeKey) {
                $attribute = $attributes[$randomAttributeKey];
                $attributeIdsByGroup[$attributeGroup->getId()][] = $attribute->getId();
            }
        }

        return $attributeIdsByGroup;
    }

    private function getAttributeGroupWithAtLeast(int $minimumValuesNumber): array
    {
        $qb = $this->entityManager
            ->getRepository(AttributeGroup::class)
            ->createQueryBuilder('ag')
        ;

        $qb
            ->select('ag.id, COUNT(a.id) AS attributesNb')
            ->leftJoin('ag.attributes', 'a')
            ->addGroupBy('ag.id')
        ;
        $attributeGroups = $qb->getQuery()->getArrayResult();
        $attributeGroupIds = array_map(static function (array $attributeGroup) {
            return (int) $attributeGroup['id'];
        }, array_filter($attributeGroups, static function (array $attributeGroup) use ($minimumValuesNumber) {
            return $attributeGroup['attributesNb'] >= $minimumValuesNumber;
        }));

        if (empty($attributeGroupIds)) {
            return [];
        }

        $qb = $this->entityManager
            ->getRepository(AttributeGroup::class)
            ->createQueryBuilder('ag')
        ;
        $qb
            ->where('ag.id IN (:attributeGroupIds)')
            ->setParameter('attributeGroupIds', array_values($attributeGroupIds), Connection::PARAM_INT_ARRAY)
        ;

        return $qb->getQuery()->getResult(Query::HYDRATE_OBJECT);
    }
}
