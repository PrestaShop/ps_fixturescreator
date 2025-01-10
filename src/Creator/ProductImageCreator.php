<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Bluemmb\Faker\PicsumPhotosProvider;
use Combination;
use Faker\Generator as Faker;
use Image;
use PrestaShop\PrestaShop\Adapter\Import\ImageCopier;

class ProductImageCreator
{
    protected Faker $faker;
    protected ImageCopier $imageCopier;

    public function __construct(Faker $faker, ImageCopier $imageCopier)
    {
        $this->faker = $faker;
        $this->faker->addProvider(new PicsumPhotosProvider($this->faker));
        $this->imageCopier = $imageCopier;
    }

    public function generate(int $number, int $productId, array $combinationsId = []): void
    {
        if ($number <= 0) {
            return;
        }

        $imageIds = [];
        for ($inc = 0; $inc < $number; ++$inc) {
            $imageId = $this->createImage($productId, $inc === 0);
            if ($imageId) {
                $imageIds[] = $imageId;
            }
        }

        foreach ($combinationsId as $combinationId) {
            $rndNumberOfImages = rand(1, $number);
            $combinationImages = $rndNumberOfImages == 1
                ? $imageIds
                : array_intersect_key($imageIds, array_flip(array_rand($imageIds, rand(2, $number))));

            $this->associateCombinationImages($combinationId, $combinationImages);
        }
    }

    public function createImage(int $productId, bool $isCover): ?int
    {
        // Download the file
        $url = $this->faker->imageUrl();
        $tmpFile = tempnam(sys_get_temp_dir(), 'data') . '.png';
        file_put_contents($tmpFile, file_get_contents($url));

        // Create the object Image
        $image = new Image();
        $image->id_product = $productId;
        $image->position = Image::getHighestPosition($productId) + 1;
        $image->cover = $isCover;
        $image->add();

        if (!$this->imageCopier->copyImg($productId, $image->id, $tmpFile, 'products', true)) {
            $image->delete();

            unlink($tmpFile);

            return null;
        }

        unlink($tmpFile);

        return (int) $image->id;
    }

    public function associateCombinationImages(int $combinationId, array $imageIds): void
    {
        $combination = new Combination($combinationId);
        $combination->setImages($imageIds);
    }
}
