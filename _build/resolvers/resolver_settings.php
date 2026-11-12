<?php
/**
 * Resolver: создание системных настроек ms3firsttimebuyerdiscount при установке/обновлении пакета.
 * name и description — ключи лексикона (lexicon/ru,en/setting.inc.php); без них в админке отображаются сырые ключи.
 *
 * @package ms3firsttimebuyerdiscount
 */

use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UNINSTALL) {
    $table = $modx->getTableName('modSystemSetting');
    $removed = $modx->exec("DELETE FROM {$table} WHERE namespace = 'ms3firsttimebuyerdiscount' AND (`key` = '' OR `key` IS NULL)");
    if ($removed > 0) {
        $modx->log(\modX::LOG_LEVEL_INFO, '[ms3FirstTimeBuyerDiscount] Cleaned ' . $removed . ' orphan setting(s) with empty key');
    }
    return true;
}

if ($options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_INSTALL
    && $options[xPDOTransport::PACKAGE_ACTION] !== xPDOTransport::ACTION_UPGRADE
) {
    return true;
}

$ns = $modx->getObject('modNamespace', ['name' => 'ms3firsttimebuyerdiscount']);
if (!$ns) {
    $ns = $modx->newObject('modNamespace');
    $ns->set('name', 'ms3firsttimebuyerdiscount');
    $ns->set('path', '{core_path}components/ms3firsttimebuyerdiscount/');
    $ns->set('assets_path', '{assets_path}components/ms3firsttimebuyerdiscount/');
    if (!$ns->save()) {
        $modx->log(\modX::LOG_LEVEL_ERROR, '[ms3firsttimebuyerdiscount] Could not create namespace ms3firsttimebuyerdiscount.');
    }
}

$definitions = [
    [
        'key' => 'ms3firsttimebuyerdiscount_ftb_enabled',
        'value' => '1',
        'xtype' => 'combo-boolean',
        'namespace' => 'ms3firsttimebuyerdiscount',
        'area' => 'ms3firsttimebuyerdiscount_main',
        'name' => 'setting_ms3firsttimebuyerdiscount_ftb_enabled',
        'description' => 'setting_ms3firsttimebuyerdiscount_ftb_enabled_desc',
    ],
    [
        'key' => 'ms3firsttimebuyerdiscount_ftb_discount_type',
        'value' => 'percent',
        'xtype' => 'textfield',
        'namespace' => 'ms3firsttimebuyerdiscount',
        'area' => 'ms3firsttimebuyerdiscount_main',
        'name' => 'setting_ms3firsttimebuyerdiscount_ftb_discount_type',
        'description' => 'setting_ms3firsttimebuyerdiscount_ftb_discount_type_desc',
    ],
    [
        'key' => 'ms3firsttimebuyerdiscount_ftb_discount_value',
        'value' => '10',
        'xtype' => 'numberfield',
        'namespace' => 'ms3firsttimebuyerdiscount',
        'area' => 'ms3firsttimebuyerdiscount_main',
        'name' => 'setting_ms3firsttimebuyerdiscount_ftb_discount_value',
        'description' => 'setting_ms3firsttimebuyerdiscount_ftb_discount_value_desc',
    ],
    [
        'key' => 'ms3firsttimebuyerdiscount_ftb_allow_combination',
        'value' => '1',
        'xtype' => 'combo-boolean',
        'namespace' => 'ms3firsttimebuyerdiscount',
        'area' => 'ms3firsttimebuyerdiscount_main',
        'name' => 'setting_ms3firsttimebuyerdiscount_ftb_allow_combination',
        'description' => 'setting_ms3firsttimebuyerdiscount_ftb_allow_combination_desc',
    ],
];

foreach ($definitions as $def) {
    $existing = $modx->getObject('modSystemSetting', ['key' => $def['key']]);
    if (!$existing) {
        $obj = $modx->newObject('modSystemSetting');
        $obj->fromArray($def, '', true, true);
        if (!$obj->save()) {
            $modx->log(\modX::LOG_LEVEL_ERROR, '[ms3firsttimebuyerdiscount] Could not create setting: ' . $def['key']);
        }
    } elseif ($def['key'] === 'ms3firsttimebuyerdiscount_ftb_discount_type' && $existing->get('xtype') === 'list') {
        $existing->set('xtype', 'textfield');
        $existing->save();
    }
}

return true;
