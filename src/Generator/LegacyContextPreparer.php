<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use Context;
use Currency;
use Employee;
use Language;
use PrestaShop\PrestaShop\Adapter\LegacyContextLoader;
use Shop;

/**
 * The legacy ObjectModel classes (Cart, Order, Product...) rely heavily on the global Context.
 * On the CLI it is empty, in the back office it is partially filled: this service makes sure the
 * shop, language, currency and employee are consistent with the requested generation context.
 */
class LegacyContextPreparer
{
    private LegacyContextLoader $legacyContextLoader;

    public function __construct(LegacyContextLoader $legacyContextLoader)
    {
        $this->legacyContextLoader = $legacyContextLoader;
    }

    public function prepare(GenerationContext $generationContext): void
    {
        $context = Context::getContext();

        if (null === $context->employee || !$context->employee->id) {
            $this->legacyContextLoader->loadGenericContext(
                null,
                Currency::getDefaultCurrency()->id,
                1
            );
            $context = Context::getContext();
        }

        if (null === $context->shop || (int) $context->shop->id !== $generationContext->getShopId()) {
            $context->shop = new Shop($generationContext->getShopId());
            Shop::setContext(Shop::CONTEXT_SHOP, $generationContext->getShopId());
        }

        if (null === $context->language || (int) $context->language->id !== $generationContext->getLangId()) {
            $context->language = new Language($generationContext->getLangId());
        }

        if (null === $context->currency || !$context->currency->id) {
            $context->currency = Currency::getDefaultCurrency();
        }

        if (null === $context->employee || !$context->employee->id) {
            $context->employee = new Employee(1);
        }

        // Generation can take a while when run over HTTP
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
    }
}
