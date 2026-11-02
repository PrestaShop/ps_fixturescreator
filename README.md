# Fixtures Creator

## About

This module loads a **heavy, realistic set of fake data** into PrestaShop so you can audit its performance: product listings on 100k products, order pages on 50k orders, statistics on 200k visitors... Then dump the database and reuse the SQL in your CI or load tests.

> [!NOTE]
> This tool replaces [PrestaShop/prestashop-shop-creator](https://github.com/PrestaShop/prestashop-shop-creator) with a different design: it calls PrestaShop's own ObjectModel / CQRS layer, so the generated rows are exactly what the application would have written itself.

It provides:

- **12 generators** covering the catalog (attributes, features, products, combinations, images, specific prices, tags, stock movements), customers (customers, addresses), sales (carts, orders with lines / carrier / status history / payments / invoices, cart rules, carriers) and customer service (threads and messages);
- **console commands** for large volumes.

## How this tool is designed and how to use it

The previous tool, prestashop-shop-creator, became progressively unmaintainable because of its XML-based design and because generating "smart" data in PHP is slow and complex.

This tool is deliberately **dumb**: each generator calls `new Product()`, `new Order()`... many times, using PrestaShop's own classes. Data is randomly linked together (products in random categories with random brands, orders of existing customers with existing carriers, threads about existing orders...), which is enough to exercise every join the application performs.

**You should not run the generation inside a CI or a performance test**. Generate once, export the database as SQL, and load that dump in your tests: predictable, fast, controlled.

## Install

```
cd modules/
git clone git@github.com:PrestaShop/ps_fixturescreator.git
cd ps_fixturescreator/
composer install
cd ../..
php bin/console prestashop:module install ps_fixturescreator
php bin/console cache:clear
```

Requirements: PrestaShop 8.0+ (tested up to 9.x), PHP 7.4+. The GD extension is used to generate product images locally (otherwise images can be downloaded from picsum.photos).


## Console

```
php bin/console prestashop:fixtures:list                        # generators and their options
php bin/console prestashop:fixtures:generate <generator> [options]
php bin/console prestashop:fixtures:stats [--json]              # same figures as the dashboard
```

Examples:

```
php bin/console prestashop:fixtures:generate products --count=20000 --batch=200 --option images=2 --option specific_prices=1
php bin/console prestashop:fixtures:generate products_with_combinations --count=2000 --option attribute_groups=2 --option attributes=6
php bin/console prestashop:fixtures:generate customers --count=10000 --option addresses_per_customer=2
php bin/console prestashop:fixtures:generate orders --count=20000 --batch=100 --option spread_months=24
```

Common options: `--shopId`, `--shopGroupId`, `--languageId`, `--batch` (split the run in batches, with a progress bar).

### Generators

| Group | Generator | Creates | Main options |
| --- | --- | --- | --- |
| Catalog | `attributes` | attribute groups with values | `values_per_group` |
| Catalog | `features` | features with values | `values_per_feature` |
| Catalog | `products` | standard products: descriptions, price, EAN, weight, tax rule, stock, random categories/brand/supplier, features, tags, specific prices, images | `categories_per_product`, `features`, `tags`, `specific_prices`, `images`, `image_source` (local/picsum), `stock_movements`, `quantity`, `spread_months` |
| Catalog | `products_with_combinations` | same, declined in combinations with their own stock, price impacts and images | `attribute_groups`, `attributes` + the product options |
| Catalog | `specific_prices` | discounts on random products (quantity, group, country, dates) | `type` (mixed/amount/percentage) |
| Catalog | `tags` | tags on random products, every language | `tags_per_product` |
| Customers | `customers` | customers (password `prestashop`) with addresses and groups | `addresses_per_customer`, `random_group`, `spread_months` |
| Sales | `carriers` | carriers for every zone/group with ranges | |
| Sales | `cart_rules` | discount codes | |
| Sales | `carts` | abandoned carts | `use_existing_customers`, `products_per_cart`, `spread_months` |
| Sales | `orders` | complete orders: lines, carrier, status history, payment, invoice; stock decremented | `use_existing_customers`, `products_per_order`, `with_invoice`, `spread_months` |
| Support | `customer_threads` | customer service threads with messages, linked to customers/orders/products | `messages_per_thread`, `link_to_customers` |

Generators pick existing data at random: generate attributes and features **before** products, and products and customers **before** carts, orders and threads.

### Legacy commands

`prestashop:shop-creator`, `prestashop:product-creator` and `prestashop:customer-thread` are kept for backward compatibility and run the same creators.

## Extending

A generator is a service implementing `FixtureGeneratorInterface` (or extending `AbstractGenerator`) tagged `ps_fixturescreator.generator` in `config/services.yml`. It declares its options with `GeneratorOption::int|bool|choice()` and returns a `GenerationResult` with the counts of what it created. It is then automatically available in the CLI and in the run history.
