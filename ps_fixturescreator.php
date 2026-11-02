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
    public const TAB_CLASS = 'AdminPsFixturesCreator';
    public const TAB_PARENT_CLASS = 'AdminAdvancedParameters';
    public const ROUTE_INDEX = 'ps_fixturescreator_index';

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
        $this->description = $this->trans('Generate large volumes of fake data (catalog, customers, orders, traffic...) to audit PrestaShop performance. Includes a dashboard of the current data volume.', [], 'Modules.Psfixturescreator.Admin');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installDatabase()
            && $this->installTab();
    }

    public function uninstall(): bool
    {
        return $this->uninstallTab()
            && $this->uninstallDatabase()
            && parent::uninstall();
    }

    /**
     * The "Configure" button of the module page redirects to the Symfony dashboard.
     */
    public function getContent(): string
    {
        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');
            Tools::redirectAdmin($router->generate(self::ROUTE_INDEX));
        } catch (Throwable $e) {
            return $this->displayError($e->getMessage());
        }

        return '';
    }

    public function installDatabase(): bool
    {
        return (bool) Db::getInstance()->execute(RunLogger::getCreateTableSql(_DB_PREFIX_, _MYSQL_ENGINE_));
    }

    public function uninstallDatabase(): bool
    {
        return (bool) Db::getInstance()->execute(RunLogger::getDropTableSql(_DB_PREFIX_));
    }

    public function installTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        $tab = new Tab($tabId ?: null);
        $tab->active = true;
        $tab->class_name = self::TAB_CLASS;
        $tab->route_name = self::ROUTE_INDEX;
        $tab->module = $this->name;
        $tab->icon = 'science';
        $tab->id_parent = (int) Tab::getIdFromClassName(self::TAB_PARENT_CLASS);
        $tab->wording = 'Fixtures Creator';
        $tab->wording_domain = 'Modules.Psfixturescreator.Admin';
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Fixtures Creator';
        }

        return (bool) $tab->save();
    }

    public function uninstallTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        if (!$tabId) {
            return true;
        }
        $tab = new Tab($tabId);

        return (bool) $tab->delete();
    }
}
