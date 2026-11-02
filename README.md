# Fixtures Creator

## About

This module loads a **heavy, realistic set of fake data** into PrestaShop so you can audit its performance: product listings on 100k products, order pages on 50k orders, statistics on 200k visitors... Then dump the database and reuse the SQL in your CI or load tests.

> [!NOTE]
> This tool replaces [PrestaShop/prestashop-shop-creator](https://github.com/PrestaShop/prestashop-shop-creator) with a different design: it calls PrestaShop's own ObjectModel / CQRS layer, so the generated rows are exactly what the application would have written itself.

It provides:

- **20 generators** covering the catalog (categories, brands, suppliers, attributes, features, products, combinations, images, specific prices, tags, stock movements), customers (customers, addresses, groups), sales (carts, orders with lines / carrier / status history / payments / invoices, cart rules, carriers), customer service (threads and messages), content (CMS, stores, employees) and traffic statistics (guests, connections, pages viewed, sources);
- a **back office page** (Configure > Advanced Parameters > Fixtures Creator) with a **dashboard** of the current data volume (entity counts, biggest tables, orders per month, generation history) and a **form** to run the generators in batches from the browser;
- **console commands** for large volumes, with ready-made presets (small / medium / large shop).

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

## Back office

Open **Configure > Advanced Parameters > Fixtures Creator** (or click *Configure* on the module).

**Dashboard tab**: counts of every entity type (products, combinations, categories, customers, addresses, carts, orders, order lines, invoices, threads, CMS pages, visitors, connections...), the biggest database tables with data/index sizes, orders per month, categories per depth, and the history of generation runs (from the UI and from the console).

**Generate tab**: pick a target shop and language, enter a quantity on each generator you need (or apply a preset), open *Options* to tune them, then run them one by one or all together. Generation is executed in small HTTP batches whose size adapts to the server speed, so it is not limited by `max_execution_time`; a browser tab must stay open. The page also shows the equivalent console commands for the current selection, and offers post-generation tasks (search indexation, category tree regeneration).

## Console

```
php bin/console prestashop:fixtures:list                        # generators, options and presets
php bin/console prestashop:fixtures:generate <generator> [options]
php bin/console prestashop:fixtures:preset <small|medium|large> [--scale=0.5] [--skip=traffic]
php bin/console prestashop:fixtures:stats [--json]              # same figures as the dashboard
```

Examples:

```
php bin/console prestashop:fixtures:generate categories --count=500 --option depth=4
php bin/console prestashop:fixtures:generate products --count=20000 --batch=200 --option images=2 --option specific_prices=1
php bin/console prestashop:fixtures:generate products_with_combinations --count=2000 --option attribute_groups=2 --option attributes=6
php bin/console prestashop:fixtures:generate customers --count=10000 --option addresses_per_customer=2
php bin/console prestashop:fixtures:generate orders --count=20000 --batch=100 --option spread_months=24
php bin/console prestashop:fixtures:generate traffic --count=100000 --batch=1000
php bin/console prestashop:fixtures:preset large --skip=traffic
```

Common options: `--shopId`, `--shopGroupId`, `--languageId`, `--batch` (split the run in batches, with a progress bar).

### Generators

| Group | Generator | Creates | Main options |
| --- | --- | --- | --- |
| Catalog | `categories` | random category tree under Home (nested set regenerated once) | `depth`, `parent` |
| Catalog | `manufacturers` | brands | |
| Catalog | `suppliers` | suppliers | |
| Catalog | `attributes` | attribute groups with values | `values_per_group` |
| Catalog | `features` | features with values | `values_per_feature` |
| Catalog | `products` | standard products: descriptions, price, EAN, weight, tax rule, stock, random categories/brand/supplier, features, tags, specific prices, images | `categories_per_product`, `features`, `tags`, `specific_prices`, `images`, `image_source` (local/picsum), `stock_movements`, `quantity`, `spread_months` |
| Catalog | `products_with_combinations` | same, declined in combinations with their own stock, price impacts and images | `attribute_groups`, `attributes` + the product options |
| Catalog | `specific_prices` | discounts on random products (quantity, group, country, dates) | `type` (mixed/amount/percentage) |
| Catalog | `tags` | tags on random products, every language | `tags_per_product` |
| Customers | `customer_groups` | groups with reductions | |
| Customers | `customers` | customers (password `prestashop`) with addresses and groups | `addresses_per_customer`, `random_group`, `spread_months` |
| Sales | `carriers` | carriers for every zone/group with ranges | |
| Sales | `cart_rules` | discount codes | |
| Sales | `carts` | abandoned carts | `use_existing_customers`, `products_per_cart`, `spread_months` |
| Sales | `orders` | complete orders: lines, carrier, status history, payment, invoice; stock decremented | `use_existing_customers`, `products_per_order`, `with_invoice`, `spread_months` |
| Support | `customer_threads` | customer service threads with messages, linked to customers/orders/products | `messages_per_thread`, `link_to_customers` |
| Content | `cms_pages` | CMS categories and pages | `cms_categories` |
| Content | `stores` | physical stores | |
| Content | `employees` | back office employees (password `prestashop_fixtures`) | |
| Traffic | `traffic` | guests, connections, pages viewed, sources | `connections_per_guest`, `pages_per_connection`, `spread_months` |

Generators pick existing data at random: generate categories, brands, attributes and features **before** products, and products and customers **before** carts, orders and threads. The presets and the "Run selected generators" button already follow that order.

### Presets

| Preset | Volume |
| --- | --- |
| `small` | ~1k products, 500 customers, 500 orders, 1k visitors |
| `medium` | ~10k products, 5k customers, 5k orders, 20k visitors |
| `large` | ~100k products, 50k customers, 50k orders, 200k visitors |

Use `--scale` to multiply every quantity (`--scale=0.1` for a dry run) and `--skip` to leave a generator out.

### Legacy commands

`prestashop:shop-creator`, `prestashop:product-creator` and `prestashop:customer-thread` are kept for backward compatibility and run the same creators.

## Extending

A generator is a service implementing `FixtureGeneratorInterface` (or extending `AbstractGenerator`) tagged `ps_fixturescreator.generator` in `config/services.yml`. It declares its options with `GeneratorOption::int|bool|choice()` and returns a `GenerationResult` with the counts of what it created. It is then automatically available in the CLI, in the back office form and in the dashboard history.
