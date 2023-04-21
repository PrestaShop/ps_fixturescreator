<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

class PsFixturesCreator extends Module
{
    public function __construct()
    {
        $this->name = 'psfixturescreator';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PS Fixtures Creator');
        $this->description = $this->l('Module to create fixtures for PrestaShop');
    }
}
