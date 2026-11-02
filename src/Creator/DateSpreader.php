<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;

/**
 * Helper to spread creation dates over the past, so stats and date filters have something to chew on.
 */
class DateSpreader
{
    private Faker $faker;

    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    /**
     * @return string Y-m-d H:i:s between now - $months months and now (or now when $months <= 0)
     */
    public function randomPastDate(int $months): string
    {
        if ($months <= 0) {
            return date('Y-m-d H:i:s');
        }

        return $this->faker->dateTimeBetween(sprintf('-%d months', $months), 'now')->format('Y-m-d H:i:s');
    }

    /**
     * @return string a date between $after and $after + $maxHours hours (capped to now)
     */
    public function randomDateAfter(string $after, int $maxHours): string
    {
        $start = new \DateTime($after);
        $end = (clone $start)->modify(sprintf('+%d hours', max(1, $maxHours)));
        $now = new \DateTime();
        if ($end > $now) {
            $end = $now;
        }
        if ($end <= $start) {
            return $start->format('Y-m-d H:i:s');
        }

        return $this->faker->dateTimeBetween($start, $end)->format('Y-m-d H:i:s');
    }
}
