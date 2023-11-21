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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PrestaShop\Module\PsFixturesCreator\Faker\FakerCategory;
use PrestaShopBundle\Entity\Attribute;
use PrestaShopBundle\Entity\AttributeGroup;
use PrestaShopBundle\Entity\AttributeGroupLang;
use PrestaShopBundle\Entity\AttributeLang;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use PrestaShopBundle\Entity\Repository\ShopRepository;
use PrestaShopBundle\Entity\Shop as ShopEntity;

class AttributeCreator
{
    private EntityManagerInterface $entityManager;

    private ShopEntity $shop;

    private LangRepository $langRepository;

    private ShopRepository $shopRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LangRepository $langRepository,
        ShopRepository $shopRepository
    ) {
        $this->entityManager = $entityManager;
        $this->langRepository = $langRepository;
        $this->shopRepository = $shopRepository;
    }

    public function generate(int $attributeGroupNumber, int $attributeValuePerGroupNumber, int $shopId): array
    {
        $this->shop = $this->shopRepository->find($shopId);
        $languages = $this->langRepository->findAll();
        $generatedGroups = [];
        $totalAttributeGroups = $this->getAttributeGroupNumber();
        for ($i = 1; $i <= $attributeGroupNumber; ++$i) {
            $fakerCategory = FakerCategory::getCategory();
            $attributeGroupOffset = $totalAttributeGroups + $i;
            $attributeGroup = $this->createAttributeGroup($attributeGroupOffset, $fakerCategory, $languages);
            for ($j = 1; $j <= $attributeValuePerGroupNumber; ++$j) {
                $this->createAttribute($j, $attributeGroupOffset, $attributeGroup, $fakerCategory, $languages);
            }

            // Flush created attributes
            $this->entityManager->flush();
            $generatedGroups[] = $attributeGroup;
        }

        return $generatedGroups;
    }

    /**
     * @param int $attributeGroupOffset
     * @param FakerCategory $fakerCategory
     * @param Lang[] $languages
     *
     * @return AttributeGroup
     */
    private function createAttributeGroup(int $attributeGroupOffset, FakerCategory $fakerCategory, array $languages): AttributeGroup
    {
        $attributeGroup = new AttributeGroup();
        $attributeGroup->setIsColorGroup(false);
        $attributeGroup->setGroupType('select');
        $attributeGroup->setPosition($attributeGroupOffset);
        $attributeGroup->addShop($this->shop);

        foreach ($languages as $lang) {
            $attributeGroupLang = new AttributeGroupLang();
            $attributeGroupLang->setName($fakerCategory->getCategoryName() . ' ' . $attributeGroupOffset . ' ' . $lang->getLocale());
            $attributeGroupLang->setPublicName($fakerCategory->getCategoryName() . ' ' . $attributeGroupOffset . ' ' . $lang->getLocale());
            $attributeGroupLang->setLang($lang);
            $attributeGroupLang->setAttributeGroup($attributeGroup);
            $attributeGroup->addAttributeGroupLang($attributeGroupLang);
            $this->entityManager->persist($attributeGroupLang);
        }

        $this->entityManager->persist($attributeGroup);
        $this->entityManager->flush();

        return $attributeGroup;
    }

    /**
     * @param int $attributeOffset
     * @param int $attributeGroupOffset
     * @param AttributeGroup $attributeGroup
     * @param FakerCategory $fakerCategory
     * @param Lang[] $languages
     */
    private function createAttribute(int $attributeOffset, int $attributeGroupOffset, AttributeGroup $attributeGroup, FakerCategory $fakerCategory, array $languages): void
    {
        $attribute = new Attribute();
        $attribute->setAttributeGroup($attributeGroup);
        $attribute->setColor('');
        $attribute->setPosition($attributeOffset);
        $attribute->addShop($this->shop);

        foreach ($languages as $lang) {
            $attributeLang = new AttributeLang();
            $attributeLang->setLang($lang);
            $attributeLang->setName($fakerCategory->getCategoryValue($lang->getLocale()) . ' ' . $attributeGroupOffset);
            $attribute->addAttributeLang($attributeLang);
            $attributeLang->setAttribute($attribute);
            $this->entityManager->persist($attributeLang);
        }
        $this->entityManager->persist($attribute);
        $attributeGroup->getAttributes()->add($attribute);
    }

    private function getAttributeGroupNumber(): int
    {
        $qb = $this->entityManager->getRepository(AttributeGroup::class)->createQueryBuilder('ag');

        return $qb
            ->select('COUNT(ag.id) AS attributeGroupNumber')
            ->getQuery()
            ->getResult(Query::HYDRATE_SINGLE_SCALAR)
        ;
    }
}
