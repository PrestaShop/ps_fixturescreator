<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

/**
 * Immutable context shared by every generator run: which shop and language to target.
 */
final class GenerationContext
{
    private int $shopId;
    private int $shopGroupId;
    private int $langId;

    public function __construct(int $shopId, int $shopGroupId, int $langId)
    {
        $this->shopId = $shopId;
        $this->shopGroupId = $shopGroupId;
        $this->langId = $langId;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getShopGroupId(): int
    {
        return $this->shopGroupId;
    }

    public function getLangId(): int
    {
        return $this->langId;
    }
}
