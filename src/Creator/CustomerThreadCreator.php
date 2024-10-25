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
use Faker\Generator as Faker;
use PrestaShop\PrestaShop\Core\Language\LanguageRepositoryInterface;

class CustomerThreadCreator
{
    protected LanguageRepositoryInterface $langRepository;
    protected Faker $faker;
    protected string $dbPrefix;
    protected Connection $connection;

    public function __construct(
        LanguageRepositoryInterface $langRepository,
        Faker $faker,
        Connection $connection,
        string $dbPrefix
    ) {
        $this->langRepository = $langRepository;
        $this->faker = $faker;
        $this->dbPrefix = $dbPrefix;
        $this->connection = $connection;
    }

    public function generate(int $threadsNumber, int $shopId): array
    {
        $languages = $this->langRepository->findAll();
        $generatedThreads = [];

        $contactsCount = $this->getEntityCount('contact');
        $productsCount = $this->getEntityCount('product');

        for ($i = 0; $i < $threadsNumber; ++$i) {
            $thread = new \CustomerThreadCore();
            $thread->id_shop = $shopId;
            $thread->id_lang = $languages[array_rand($languages)]->getId();
            $thread->id_contact = $this->getRandomIdFromCount($contactsCount);
            $thread->id_customer = 0;
            $thread->id_order = 0;
            $thread->id_product = rand(0, 1) ? $this->getRandomIdFromCount($productsCount) : null;
            $thread->email = $this->faker->email;
            $thread->token = md5(uniqid());
            $thread->status = 'open';
            $thread->date_add = $this->faker->dateTimeBetween('2020-01-01', 'now')->format('Y-m-d H:i:s');
            $thread->date_upd = date('Y-m-d H:i:s');

            $thread->add(false);

            $generatedThreads[] = $thread;
        }

        return $generatedThreads;
    }

    private function getEntityCount(string $tableName): int
    {
        $count = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS count')
            ->from($this->dbPrefix . $tableName)
            ->execute()
            ->fetchOne();

        return (int) $count;
    }

    private function getRandomIdFromCount(int $count): ?int
    {
        if ($count <= 0) {
            return null;
        }

        return rand(1, $count);
    }
}
