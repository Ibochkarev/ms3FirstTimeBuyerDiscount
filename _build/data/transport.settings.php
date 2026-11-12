<?php
/**
 * Системные настройки ms3firsttimebuyerdiscount
 */
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

$settings = [];
foreach ($definitions as $def) {
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray($def, '', true, true);
    $settings[] = $setting;
}
return $settings;
