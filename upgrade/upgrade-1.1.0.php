<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.1.0: back office dashboard + generation log table.
 *
 * @param ps_fixturescreator $module
 */
function upgrade_module_1_1_0($module): bool
{
    return $module->installDatabase() && $module->installTab();
}
