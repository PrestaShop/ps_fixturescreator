<?php

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Faker\Generator;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Entity\Attribute;
use PrestaShopBundle\Entity\AttributeGroup;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;

class ProductCombinationCreator
{
    private EntityManagerInterface $entityManager;

    private CommandBusInterface $commandBus;

    private AttributeCreator $attributeCreator;

    private Generator $faker;

    private LangRepository $langRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommandBusInterface $commandBus,
        AttributeCreator $attributeCreator,
        LangRepository $langRepository,
        Generator $faker
    ) {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->attributeCreator = $attributeCreator;
        $this->langRepository = $langRepository;
        $this->faker = $faker;
    }

    public function generate(int $productWithCombinations, int $attributeGroupNumber, int $attributeValuePerGroupNumber, int $shopId): void
    {
        $attributeGroups = $this->getAttributeGroupWithAtLeast($attributeValuePerGroupNumber);
        // Create the missing attribute groups if needed
        if (count($attributeGroups) < $attributeGroupNumber) {
            $attributeGroups = array_merge(
                $attributeGroups,
                $this->attributeCreator->generate($attributeGroupNumber - count($attributeGroups), $attributeValuePerGroupNumber, $shopId)
            );
        }

        for ($i = 1; $i <= $productWithCombinations; ++$i) {
            $combinationAttributes = $this->getAttributesForGeneration($attributeGroups, $attributeGroupNumber, $attributeValuePerGroupNumber);
            $productName = $this->faker->productName;
            $productNames = [];
            /** @var Lang $lang */
            foreach ($this->langRepository->findAll() as $lang) {
                $productNames[$lang->getId()] = $productName . ' ' . $lang->getLocale();
            }

            /** @var ProductId $newProductId */
            $newProductId = $this->commandBus->handle(new AddProductCommand(
                ProductType::TYPE_COMBINATIONS,
                $shopId,
                $productNames
            ));

            $this->commandBus->handle(new GenerateProductCombinationsCommand(
                $newProductId->getValue(),
                $combinationAttributes,
                $shopId ? ShopConstraint::shop($shopId) : ShopConstraint::allShops()
            ));
        }
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
        $randomAttributeGroupsKeys = array_rand($attributeGroups, $attributeGroupNumber);
        foreach ($randomAttributeGroupsKeys as $randomAttributeGroupKey) {
            $attributeGroup = $attributeGroups[$randomAttributeGroupKey];
            $attributeIdsByGroup[$attributeGroup->getId()] = [];

            /** @var Attribute[] $attributes */
            $attributes = $attributeGroup->getAttributes()->toArray();
            $randomAttributeKeys = array_rand($attributes, $attributeValuePerGroupNumber);
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

        $qb = $this->entityManager
            ->getRepository(AttributeGroup::class)
            ->createQueryBuilder('ag')
        ;
        $qb
            ->where('ag.id IN (:attributeGroupIds)')
            ->setParameter('attributeGroupIds', array_values($attributeGroupIds), ArrayParameterType::INTEGER)
        ;

        return $qb->getQuery()->getResult(Query::HYDRATE_OBJECT);
    }
}
