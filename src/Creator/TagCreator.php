<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Faker\Generator as Faker;
use PrestaShopBundle\Entity\Repository\LangRepository;
use Tag;

/**
 * Tags products with words taken from a shared vocabulary, so tags are reused across products
 * (like a real shop) instead of being unique per product.
 */
class TagCreator
{
    private const VOCABULARY_SIZE = 400;

    private Faker $faker;
    private LangRepository $langRepository;
    private EntityPicker $entityPicker;

    /** @var string[] */
    private array $vocabulary = [];

    public function __construct(Faker $faker, LangRepository $langRepository, EntityPicker $entityPicker)
    {
        $this->faker = $faker;
        $this->langRepository = $langRepository;
        $this->entityPicker = $entityPicker;
    }

    /**
     * Tags $number random existing products.
     *
     * @return int number of product/tag links created
     */
    public function generate(int $number, int $tagsPerProduct, int $shopId): int
    {
        $productIds = $this->entityPicker->getProductIds($shopId);
        $created = 0;
        foreach ($this->entityPicker->pickRandom($productIds, $number) as $productId) {
            $created += $this->tagProduct($productId, $tagsPerProduct);
        }

        return $created;
    }

    /**
     * @return int number of product/tag links created
     */
    public function tagProduct(int $productId, int $tagsPerProduct): int
    {
        if ($tagsPerProduct <= 0) {
            return 0;
        }

        $vocabulary = $this->getVocabulary();
        $created = 0;
        foreach ($this->langRepository->findAll() as $lang) {
            $tags = $this->entityPicker->pickRandom(array_keys($vocabulary), $tagsPerProduct);
            $tagNames = array_map(static function (int $index) use ($vocabulary): string {
                return $vocabulary[$index];
            }, $tags);
            try {
                if (Tag::addTags($lang->getId(), $productId, $tagNames)) {
                    $created += count($tagNames);
                }
            } catch (\Throwable $e) {
                // The product already has one of these tags: ignore
            }
        }

        return $created;
    }

    /**
     * @return string[]
     */
    private function getVocabulary(): array
    {
        if (empty($this->vocabulary)) {
            // Faker's lorem dictionary has fewer distinct words than VOCABULARY_SIZE: combine two words
            // when needed and cap the attempts so this can never loop forever.
            $words = [];
            $attempts = 0;
            while (count($words) < self::VOCABULARY_SIZE && $attempts < self::VOCABULARY_SIZE * 10) {
                ++$attempts;
                $word = $attempts <= self::VOCABULARY_SIZE ? $this->faker->word : $this->faker->word . ' ' . $this->faker->word;
                $words[$word] = true;
            }
            $this->vocabulary = array_keys($words);
        }

        return $this->vocabulary;
    }
}
