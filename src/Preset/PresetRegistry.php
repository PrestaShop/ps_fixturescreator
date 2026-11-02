<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Preset;

/**
 * Ready-made volumes to reach a given shop size in one go. Each step is a generator run.
 */
class PresetRegistry
{
    /**
     * @return array<string, array{label: string, description: string, steps: array<int, array{generator: string, count: int, options: array<string, mixed>}>}>
     */
    public function all(): array
    {
        return [
            'small' => [
                'label' => 'Small shop',
                'description' => '~1k products, 500 customers, 500 orders. Quick smoke test (a few minutes).',
                'steps' => [
                    ['generator' => 'categories', 'count' => 50, 'options' => ['depth' => 3]],
                    ['generator' => 'manufacturers', 'count' => 20, 'options' => []],
                    ['generator' => 'suppliers', 'count' => 10, 'options' => []],
                    ['generator' => 'attributes', 'count' => 5, 'options' => ['values_per_group' => 8]],
                    ['generator' => 'features', 'count' => 10, 'options' => ['values_per_feature' => 10]],
                    ['generator' => 'customer_groups', 'count' => 3, 'options' => []],
                    ['generator' => 'carriers', 'count' => 3, 'options' => []],
                    ['generator' => 'products', 'count' => 800, 'options' => ['images' => 1]],
                    ['generator' => 'products_with_combinations', 'count' => 200, 'options' => ['attribute_groups' => 2, 'attributes' => 4, 'images' => 1]],
                    ['generator' => 'specific_prices', 'count' => 500, 'options' => []],
                    ['generator' => 'customers', 'count' => 500, 'options' => ['addresses_per_customer' => 1]],
                    ['generator' => 'cart_rules', 'count' => 20, 'options' => []],
                    ['generator' => 'carts', 'count' => 200, 'options' => []],
                    ['generator' => 'orders', 'count' => 500, 'options' => []],
                    ['generator' => 'customer_threads', 'count' => 100, 'options' => ['messages_per_thread' => 3]],
                    ['generator' => 'cms_pages', 'count' => 20, 'options' => ['cms_categories' => 2]],
                    ['generator' => 'traffic', 'count' => 1000, 'options' => []],
                ],
            ],
            'medium' => [
                'label' => 'Medium shop',
                'description' => '~10k products, 5k customers, 5k orders, 20k visitors. Typical mid-size merchant.',
                'steps' => [
                    ['generator' => 'categories', 'count' => 300, 'options' => ['depth' => 4]],
                    ['generator' => 'manufacturers', 'count' => 100, 'options' => []],
                    ['generator' => 'suppliers', 'count' => 50, 'options' => []],
                    ['generator' => 'attributes', 'count' => 10, 'options' => ['values_per_group' => 10]],
                    ['generator' => 'features', 'count' => 30, 'options' => ['values_per_feature' => 15]],
                    ['generator' => 'customer_groups', 'count' => 5, 'options' => []],
                    ['generator' => 'carriers', 'count' => 5, 'options' => []],
                    ['generator' => 'products', 'count' => 8000, 'options' => ['images' => 2, 'specific_prices' => 1]],
                    ['generator' => 'products_with_combinations', 'count' => 2000, 'options' => ['attribute_groups' => 2, 'attributes' => 5, 'images' => 2]],
                    ['generator' => 'specific_prices', 'count' => 5000, 'options' => []],
                    ['generator' => 'customers', 'count' => 5000, 'options' => ['addresses_per_customer' => 2]],
                    ['generator' => 'cart_rules', 'count' => 100, 'options' => []],
                    ['generator' => 'carts', 'count' => 2000, 'options' => []],
                    ['generator' => 'orders', 'count' => 5000, 'options' => []],
                    ['generator' => 'customer_threads', 'count' => 1000, 'options' => ['messages_per_thread' => 4]],
                    ['generator' => 'cms_pages', 'count' => 50, 'options' => ['cms_categories' => 5]],
                    ['generator' => 'stores', 'count' => 20, 'options' => []],
                    ['generator' => 'employees', 'count' => 20, 'options' => []],
                    ['generator' => 'traffic', 'count' => 20000, 'options' => []],
                ],
            ],
            'large' => [
                'label' => 'Large shop',
                'description' => '~100k products, 50k customers, 50k orders, 200k visitors. Where most bottlenecks appear. Several hours.',
                'steps' => [
                    ['generator' => 'categories', 'count' => 2000, 'options' => ['depth' => 5]],
                    ['generator' => 'manufacturers', 'count' => 500, 'options' => []],
                    ['generator' => 'suppliers', 'count' => 200, 'options' => []],
                    ['generator' => 'attributes', 'count' => 20, 'options' => ['values_per_group' => 15]],
                    ['generator' => 'features', 'count' => 100, 'options' => ['values_per_feature' => 20]],
                    ['generator' => 'customer_groups', 'count' => 10, 'options' => []],
                    ['generator' => 'carriers', 'count' => 10, 'options' => []],
                    ['generator' => 'products', 'count' => 80000, 'options' => ['images' => 2, 'specific_prices' => 1]],
                    ['generator' => 'products_with_combinations', 'count' => 20000, 'options' => ['attribute_groups' => 2, 'attributes' => 6, 'images' => 2]],
                    ['generator' => 'specific_prices', 'count' => 50000, 'options' => []],
                    ['generator' => 'customers', 'count' => 50000, 'options' => ['addresses_per_customer' => 2]],
                    ['generator' => 'cart_rules', 'count' => 500, 'options' => []],
                    ['generator' => 'carts', 'count' => 20000, 'options' => []],
                    ['generator' => 'orders', 'count' => 50000, 'options' => []],
                    ['generator' => 'customer_threads', 'count' => 10000, 'options' => ['messages_per_thread' => 4]],
                    ['generator' => 'cms_pages', 'count' => 200, 'options' => ['cms_categories' => 10]],
                    ['generator' => 'stores', 'count' => 100, 'options' => []],
                    ['generator' => 'employees', 'count' => 50, 'options' => []],
                    ['generator' => 'traffic', 'count' => 200000, 'options' => []],
                ],
            ],
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    /**
     * @return array{label: string, description: string, steps: array<int, array{generator: string, count: int, options: array<string, mixed>}>}
     */
    public function get(string $name): array
    {
        $presets = $this->all();
        if (!isset($presets[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown preset "%s". Available presets: %s', $name, implode(', ', array_keys($presets))));
        }

        return $presets[$name];
    }
}
