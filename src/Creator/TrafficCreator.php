<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Doctrine\DBAL\Connection;
use Faker\Generator as Faker;

/**
 * Generates visitor statistics: guests, connections, pages viewed and traffic sources.
 * On real shops these are by far the biggest tables, so they are inserted with raw multi-row SQL.
 */
class TrafficCreator
{
    private const REFERERS = [
        'https://www.google.com/',
        'https://www.google.fr/',
        'https://www.bing.com/',
        'https://duckduckgo.com/',
        'https://www.facebook.com/',
        'https://www.instagram.com/',
        'https://t.co/',
        null,
        null,
        null,
    ];

    private Faker $faker;
    private Connection $connection;
    private string $dbPrefix;
    private EntityPicker $entityPicker;
    private DateSpreader $dateSpreader;

    public function __construct(Faker $faker, Connection $connection, string $dbPrefix, EntityPicker $entityPicker, DateSpreader $dateSpreader)
    {
        $this->faker = $faker;
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
        $this->entityPicker = $entityPicker;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * @return array{guests: int, connections: int, connections_pages: int, connections_sources: int}
     */
    public function generate(int $guestsNumber, int $connectionsPerGuest, int $pagesPerConnection, int $spreadMonths, int $shopId, int $shopGroupId): array
    {
        $counters = ['guests' => 0, 'connections' => 0, 'connections_pages' => 0, 'connections_sources' => 0];
        $pageIds = $this->ensurePages($shopId);
        $customerIds = $this->entityPicker->getCustomerIds();

        for ($i = 0; $i < $guestsNumber; ++$i) {
            $guestId = $this->createGuest($this->faker->boolean(30) ? $this->entityPicker->pickOne($customerIds) : null);
            ++$counters['guests'];

            $connections = $this->faker->numberBetween(1, max(1, $connectionsPerGuest));
            for ($j = 0; $j < $connections; ++$j) {
                $date = $this->dateSpreader->randomPastDate($spreadMonths);
                $referer = $this->faker->randomElement(self::REFERERS);
                $connectionId = $this->createConnection($guestId, (int) $pageIds[array_rand($pageIds)], $date, $referer, $shopId, $shopGroupId);
                ++$counters['connections'];

                $counters['connections_pages'] += $this->createConnectionPages($connectionId, $pageIds, $pagesPerConnection, $date);

                if (null !== $referer) {
                    $this->connection->insert($this->dbPrefix . 'connections_source', [
                        'id_connections' => $connectionId,
                        'http_referer' => $referer,
                        'request_uri' => '/' . $this->faker->slug(2),
                        'keywords' => $this->faker->words(2, true),
                        'date_add' => $date,
                    ]);
                    ++$counters['connections_sources'];
                }
            }
        }

        return $counters;
    }

    private function createGuest(?int $customerId): int
    {
        $this->connection->insert($this->dbPrefix . 'guest', [
            'id_operating_system' => $this->faker->numberBetween(1, 8),
            'id_web_browser' => $this->faker->numberBetween(1, 12),
            'id_customer' => $customerId ?? 0,
            'javascript' => 1,
            'screen_resolution_x' => $this->faker->randomElement([1366, 1440, 1536, 1920, 2560, 390, 412]),
            'screen_resolution_y' => $this->faker->randomElement([768, 900, 864, 1080, 1440, 844, 915]),
            'screen_color' => 24,
            'sun_java' => 0,
            'adobe_flash' => 0,
            'adobe_director' => 0,
            'apple_quicktime' => 0,
            'real_player' => 0,
            'windows_media' => 0,
            'accept_language' => $this->faker->randomElement(['fr', 'en', 'en-US', 'de', 'es', 'it']),
            'mobile_theme' => $this->faker->boolean(40) ? 1 : 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createConnection(int $guestId, int $pageId, string $date, ?string $referer, int $shopId, int $shopGroupId): int
    {
        $this->connection->insert($this->dbPrefix . 'connections', [
            'id_shop_group' => $shopGroupId,
            'id_shop' => $shopId,
            'id_guest' => $guestId,
            'id_page' => $pageId,
            'ip_address' => ip2long($this->faker->ipv4),
            'date_add' => $date,
            'http_referer' => $referer,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param int[] $pageIds
     */
    private function createConnectionPages(int $connectionId, array $pageIds, int $pagesPerConnection, string $date): int
    {
        $pages = $this->faker->numberBetween(1, max(1, $pagesPerConnection));
        $picked = $this->entityPicker->pickRandom($pageIds, $pages);
        if (empty($picked)) {
            return 0;
        }

        $values = [];
        $start = new \DateTime($date);
        foreach ($picked as $pageId) {
            $end = (clone $start)->modify(sprintf('+%d seconds', $this->faker->numberBetween(5, 300)));
            $values[] = sprintf(
                '(%d, %d, %s, %s)',
                $connectionId,
                $pageId,
                $this->connection->quote($start->format('Y-m-d H:i:s')),
                $this->connection->quote($end->format('Y-m-d H:i:s'))
            );
            $start = $end;
        }

        $this->connection->executeStatement(sprintf(
            'INSERT IGNORE INTO `%sconnections_page` (id_connections, id_page, time_start, time_end) VALUES %s',
            $this->dbPrefix,
            implode(',', $values)
        ));

        return count($picked);
    }

    /**
     * Makes sure there are page rows to reference (product pages, category pages, index...).
     *
     * @return int[]
     */
    private function ensurePages(int $shopId): array
    {
        $pageIds = $this->entityPicker->getPageIds();
        if (count($pageIds) >= 50) {
            return $pageIds;
        }

        $pageTypes = [];
        foreach ($this->connection->executeQuery(sprintf('SELECT id_page_type, name FROM `%spage_type`', $this->dbPrefix))->fetchAllAssociative() as $row) {
            $pageTypes[$row['name']] = (int) $row['id_page_type'];
        }
        foreach (['index', 'product', 'category', 'cms', 'search', 'cart', 'order', 'contact'] as $type) {
            if (!isset($pageTypes[$type])) {
                $this->connection->insert($this->dbPrefix . 'page_type', ['name' => $type]);
                $pageTypes[$type] = (int) $this->connection->lastInsertId();
            }
        }

        $values = [];
        foreach (['index', 'search', 'cart', 'order', 'contact'] as $type) {
            $values[] = sprintf('(%d, NULL)', $pageTypes[$type]);
        }
        foreach ($this->entityPicker->pickRandom($this->entityPicker->getProductIds($shopId), 200) as $productId) {
            $values[] = sprintf('(%d, %d)', $pageTypes['product'], $productId);
        }
        foreach ($this->entityPicker->pickRandom($this->entityPicker->getCategoryIds($shopId, 1), 50) as $categoryId) {
            $values[] = sprintf('(%d, %d)', $pageTypes['category'], $categoryId);
        }
        $this->connection->executeStatement(sprintf('INSERT IGNORE INTO `%spage` (id_page_type, id_object) VALUES %s', $this->dbPrefix, implode(',', $values)));

        return $this->entityPicker->getPageIds();
    }
}
