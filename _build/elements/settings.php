<?php

return [
    'ftb_enabled' => [
        'xtype' => 'combo-boolean',
        'value' => true,
        'area' => 'ms3firsttimebuyerdiscount_main',
    ],
    'ftb_discount_type' => [
        'xtype' => 'list',
        'value' => 'percent',
        'options' => 'percent==Процент||fixed==Фиксированная сумма',
        'area' => 'ms3firsttimebuyerdiscount_main',
    ],
    'ftb_discount_value' => [
        'xtype' => 'number',
        'value' => 10,
        'area' => 'ms3firsttimebuyerdiscount_main',
    ],
    'ftb_allow_combination' => [
        'xtype' => 'combo-boolean',
        'value' => true,
        'area' => 'ms3firsttimebuyerdiscount_main',
    ],
];
