<?php

use PrestaShop\Module\PsFixturesCreator\Statistics\RunLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class ps_fixturescreator extends Module
{
    public function __construct()
    {
        $this->name = 'ps_fixturescreator';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('PS Fixtures Creator', [], 'Modules.Psfixturescreator.Admin');
        $this->description = $this->trans('Generate large volumes of fake data (catalog, customers, orders, traffic...) to audit PrestaShop performance.', [], 'Modules.Psfixturescreator.Admin');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installDatabase();
    }

    public function uninstall(): bool
    {
        return $this->uninstallDatabase()
            && parent::uninstall();
    }

    public function installDatabase(): bool
    {
        return (bool) Db::getInstance()->execute(RunLogger::getCreateTableSql(_DB_PREFIX_, _MYSQL_ENGINE_));
    }

    public function uninstallDatabase(): bool
    {
        return (bool) Db::getInstance()->execute(RunLogger::getDropTableSql(_DB_PREFIX_));
    }
}
