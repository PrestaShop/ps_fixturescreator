# Fixtures Creator

## About

This module was desiged to load an heavy set of data into PrestaShop. Then you can use the SQL dump to easily "load" another shop with lot of data for performance testing.

### How to use it

Install the module:
- use `git clone` to clone this repository inside `modules/` folder
- make sure the folder name is `psfixturescreator` (the folder name and the main PHP module name must match)

A new Command should be available when you run `php bin/console`:
```
php bin/console prestashop:shop-creator
```

Running this command will load the data. Please check options with `-h` flag to see available parameters.