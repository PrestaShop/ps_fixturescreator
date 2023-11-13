<?php

namespace PrestaShop\Module\PsFixturesCreator;

use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Entity\Attribute;
use PrestaShopBundle\Entity\AttributeGroup;
use PrestaShopBundle\Entity\AttributeGroupLang;
use PrestaShopBundle\Entity\AttributeLang;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Shop as ShopEntity;

class ProductCombinationCreator
{
    private EntityManagerInterface $entityManager;

    private CommandBusInterface $commandBus;

    private Lang $lang;

    private ShopEntity $shop;

    public function __construct(EntityManagerInterface $entityManager, CommandBusInterface $commandBus)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
    }

    public function generate(int $attributeGroupNumber, int $attributeValuePerGroupNumber, int $targetProductId, int $langId, int $shopId): void
    {
        $this->lang = $this->entityManager->getRepository(Lang::class)->find($langId);
        $this->shop = $this->entityManager->getRepository(ShopEntity::class)->find($shopId);
        for ($i = 1; $i <= $attributeGroupNumber; ++$i) {
            $attributeGroup = $this->createAttributeGroup($i);
            $attributeGroupIdList = [];
            for ($j = 1; $j <= $attributeValuePerGroupNumber; ++$j) {
                $attribute = $this->createAttribute($j, $attributeGroup);
                $attributeGroupIdList[] = $attribute->getId();
            }
            $attributes = [];
            $requestAttributeGroups = [];
            $requestAttributeGroups[$attributeGroup->getId()] = $attributeGroupIdList;

            foreach ($requestAttributeGroups as $attributeGroupId => $requestAttributes) {
                $attributes[(int) $attributeGroupId] = array_map('intval', $requestAttributes);
            }

            $this->commandBus->handle(new GenerateProductCombinationsCommand(
                $targetProductId,
                $attributes,
                $shopId ? ShopConstraint::shop($shopId) : ShopConstraint::allShops()
            ));
        }
    }

    private function createAttributeGroup(int $attributeGroupNumber): AttributeGroup
    {
        $attributeGroupLang = new AttributeGroupLang();
        $attributeGroupLang->setName('fake_attribute_' . (string) $attributeGroupNumber);
        $attributeGroupLang->setPublicName('fake_attribute_' . (string) $attributeGroupNumber);
        $attributeGroupLang->setLang($this->lang);

        $attributeGroup = new AttributeGroup();
        $attributeGroup->setIsColorGroup(false);
        $attributeGroup->setGroupType('select');
        $attributeGroup->setPosition($attributeGroupNumber);
        $attributeGroup->addShop($this->shop);
        $attributeGroup->addAttributeGroupLang($attributeGroupLang);
        $attributeGroupLang->setAttributeGroup($attributeGroup);

        $this->entityManager->persist($attributeGroup);
        $this->entityManager->persist($attributeGroupLang);

        $this->entityManager->flush();

        return $attributeGroup;
    }

    private function createAttribute(int $attributeNumber, AttributeGroup $attributeGroup): Attribute
    {
        $attributeLang = new AttributeLang();
        $attributeLang->setLang($this->lang);
        $attributeLang->setName('fake_attribute_value_' . (string) $attributeNumber);

        $attribute = new Attribute();
        $attribute->setAttributeGroup($attributeGroup);
        $attribute->setColor('');
        $attribute->setPosition($attributeNumber);
        $attribute->addShop($this->shop);
        $attribute->addAttributeLang($attributeLang);
        $attributeLang->setAttribute($attribute);

        $this->entityManager->persist($attribute);
        $this->entityManager->persist($attributeLang);

        $this->entityManager->flush();

        return $attribute;
    }
}
