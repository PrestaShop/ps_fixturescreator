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
    public const SOURCE_LOCAL = 'local';
    public const SOURCE_PICSUM = 'picsum';

    protected Faker $faker;
    protected ImageCopier $imageCopier;
    protected string $source = self::SOURCE_LOCAL;

    public function __construct(Faker $faker, ImageCopier $imageCopier)
    {
        $this->faker = $faker;
        $this->faker->addProvider(new PicsumPhotosProvider($this->faker));
        $this->imageCopier = $imageCopier;
    }

    /**
     * @param string $source self::SOURCE_LOCAL (generated with GD, no network) or self::SOURCE_PICSUM (downloaded photos)
     */
    public function setSource(string $source): void
    {
        $this->source = $source === self::SOURCE_PICSUM ? self::SOURCE_PICSUM : self::SOURCE_LOCAL;
    }

    /**
     * @param int[] $combinationsId
     *
     * @return int number of images created
     */
    public function generate(int $number, int $productId, array $combinationsId = []): int
    {
        if ($number <= 0) {
            return 0;
        }

        $imageIds = [];
        for ($inc = 0; $inc < $number; ++$inc) {
            $imageId = $this->createImage($productId, $inc === 0);
            if ($imageId) {
                $imageIds[] = $imageId;
            }
        }

        if (empty($imageIds)) {
            return 0;
        }

        foreach ($combinationsId as $combinationId) {
            $rndNumberOfImages = rand(1, count($imageIds));
            $combinationImages = $rndNumberOfImages <= 1
                ? $imageIds
                : array_intersect_key($imageIds, array_flip((array) array_rand($imageIds, $rndNumberOfImages)));

            $this->associateCombinationImages($combinationId, array_values($combinationImages));
        }

        return count($imageIds);
    }

    public function createImage(int $productId, bool $isCover): ?int
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'data') . '.png';
        if (!$this->writeImageFile($tmpFile, $productId)) {
            @unlink($tmpFile);

            return null;
        }

        $image = new Image();
        $image->id_product = $productId;
        $image->position = Image::getHighestPosition($productId) + 1;
        $image->cover = $isCover;
        $image->add();

        if (!$this->imageCopier->copyImg($productId, $image->id, $tmpFile, 'products', true)) {
            $image->delete();
            @unlink($tmpFile);

            return null;
        }

        @unlink($tmpFile);

        return (int) $image->id;
    }

    /**
     * @param int[] $imageIds
     */
    public function associateCombinationImages(int $combinationId, array $imageIds): void
    {
        $combination = new Combination($combinationId);
        $combination->setImages($imageIds);
    }

    private function writeImageFile(string $path, int $productId): bool
    {
        if (self::SOURCE_PICSUM === $this->source) {
            $content = @file_get_contents($this->faker->imageUrl());
            if (false !== $content && '' !== $content) {
                return false !== file_put_contents($path, $content);
            }
            // Network issue: fall back to a generated image
        }

        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $size = 800;
        $image = imagecreatetruecolor($size, $size);
        $background = imagecolorallocate($image, rand(30, 225), rand(30, 225), rand(30, 225));
        imagefill($image, 0, 0, $background);

        // A few random shapes so every image is different and JPEG/PNG compression has some work to do
        for ($i = 0; $i < 12; ++$i) {
            $color = imagecolorallocatealpha($image, rand(0, 255), rand(0, 255), rand(0, 255), rand(20, 90));
            $x = rand(0, $size);
            $y = rand(0, $size);
            $radius = rand(40, 300);
            if (rand(0, 1)) {
                imagefilledellipse($image, $x, $y, $radius, $radius, $color);
            } else {
                imagefilledrectangle($image, $x, $y, min($size, $x + $radius), min($size, $y + $radius), $color);
            }
        }

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $label = sprintf('#%d', $productId);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        imagestring($image, $font, (int) (($size - $textWidth) / 2), (int) ($size / 2) - 8, $label, $textColor);

        $result = imagepng($image, $path);
        imagedestroy($image);

        return $result;
    }
}
