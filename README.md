# Fixtures Creator

## About

This module was desiged to load an heavy set of data into PrestaShop. Then you can use the SQL dump to easily "load" another shop with lot of data for performance testing.

## Install

### Install and configure module

Install the module by using `git clone` to clone this repository inside `modules/` folder.

### Bash steps

Here is how to do the previous steps all in CLI, from the shop root folder:

```
cd modules/
git clone git@github.com:PrestaShop/ps_fixturescreator.git .
cd ps_fixturescreator/
composer install
cd ../..
php bin/console prestashop:module install ps_fixturescreator
php bin/console cache:clear
```

## Usage

A new Command should be available when you run `php bin/console`:
```
php bin/console prestashop:shop-creator
```

Running this command will load the data. Please check options with `-h` flag to see available parameters.
