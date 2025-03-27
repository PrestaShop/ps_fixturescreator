# Fixtures Creator

## About

This module was desiged to load an heavy set of data into PrestaShop. Then you can use the SQL dump to easily "load" another shop with lot of data for performance testing.

> [!NOTE]
> This tool aims to replace [PrestaShop/prestashop-shop-creator](https://github.com/PrestaShop/prestashop-shop-creator) by using a different design. See below.

## How this tool is designed and how to use it

The previous tool, prestashop-shop-creator, became progressively unmaintainable. We believe this is because of
- how it was designed in the beginning (with XML files at its core)
- a PHP generation tool will always be slower than directly injecting raw SQL into database
- generating 'smart' data (carts with real products and real customers and real adresses) is complex

This tool has then been created in a 'dummy' way on purpose: all it does is call `new` on ObjectModel plenty times to create the needed objects.

**You should not use the generation action inside a CI or a performance test**. Instead, use the tool to load the shop with your fake data, then export the data as SQL files. Use these SQL files in your CI or performance tests: it will be predictible, fast, and controlled.

## Install

### Install and configure module

Install the module by using `git clone` to clone this repository inside `modules/` folder.

### Bash steps

Here is how to do the previous steps all in CLI, from the shop root folder:

```
cd modules/
git clone git@github.com:PrestaShop/ps_fixturescreator.git 
cd ps_fixturescreator/
composer install
cd ../..
php bin/console prestashop:module install ps_fixturescreator
php bin/console cache:clear
```

## Usage

New commands should be available when you run `php bin/console`:

```
php bin/console prestashop:shop-creator

Options:
      --orders[=ORDERS]                    Number of orders to create [default: 0]
      --customers[=CUSTOMERS]              Number of customers without order to create [default: 0]
      --carts[=CARTS]                      Number of carts to create [default: 0]
      --cart-rules[=CART-RULES]            Number of cart rules to create [default: 0]
      --shopId[=SHOPID]                    The shop identifier [default: 1]
      --shopGroupId[=SHOPGROUPID]          The shop group identifier [default: 1]
      --languageId[=LANGUAGEID]            The languageId identifier [default: 1]
      --attributeGroups[=ATTRIBUTEGROUPS]  Number of attribute groups [default: 0]
      --attributes[=ATTRIBUTES]            Number of attributes per attribute group [default: 10]
      --features[=FEATURES]                Number of features [default: 0]
      --featureValues[=FEATUREVALUES]      Number of values per feature [default: 10]
      --threads[=THREADS]                  Number of threads to create [default: 0]
```

```
php bin/console prestashop:shop-creator

Options:
      --products[=PRODUCTS]                                  Number of products to create [default: 0]
      --productsWithCombinations[=PRODUCTSWITHCOMBINATIONS]  Number of products with combinations to create [default: 0]
      --shopId[=SHOPID]                                      The shop identifier [default: 1]
      --shopGroupId[=SHOPGROUPID]                            The shop group identifier [default: 1]
      --attributeGroups[=ATTRIBUTEGROUPS]                    Number of attribute groups per product [default: 2]
      --attributes[=ATTRIBUTES]                              Number of attributes per attribute group [default: 5]
      --features[=FEATURES]                                  Number of features per product [default: 2]
      --featureValues[=FEATUREVALUES]                        Number of values per feature [default: 5]
      --stockMovements[=STOCKMOVEMENTS]                      Number of stock movements per product [default: 0]
      --images[=IMAGES]                                      Number of images per product [default: 0]
```

```
php bin/console prestashop:customer-thread

Options:
      --threads[=THREADS]  Number of threads to create [default: 0]
      --shopId[=SHOPID]    The shop identifier [default: 1]
```
