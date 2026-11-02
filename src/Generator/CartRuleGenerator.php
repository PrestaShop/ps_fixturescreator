<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

use PrestaShop\Module\PsFixturesCreator\Creator\CartRuleCreator;

class CartRuleGenerator extends AbstractGenerator
{
    private CartRuleCreator $cartRuleCreator;

    public function __construct(CartRuleCreator $cartRuleCreator)
    {
        $this->cartRuleCreator = $cartRuleCreator;
    }

    public function getName(): string
    {
        return 'cart_rules';
    }

    public function getLabel(): string
    {
        return 'Cart rules';
    }

    public function getDescription(): string
    {
        return 'Active discount codes with random percentage, minimum amount and validity dates. Each one is evaluated on every cart update.';
    }

    public function getGroup(): string
    {
        return self::GROUP_SALES;
    }

    public function getCountLabel(): string
    {
        return 'Number of cart rules';
    }

    public function getBatchSize(): int
    {
        return 100;
    }

    public function getPriority(): int
    {
        return 71;
    }

    public function generate(int $count, array $options, GenerationContext $context): GenerationResult
    {
        $this->cartRuleCreator->generate($count, $context->getLangId());

        return (new GenerationResult())->add('cart_rules', $count);
    }
}
