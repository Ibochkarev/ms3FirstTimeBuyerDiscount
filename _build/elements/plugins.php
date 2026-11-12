<?php

return [
    'ms3FirstTimeBuyerDiscount' => [
        'file' => 'ftb_discount',
        'description' => 'Скидка для первого заказа (First-Time Buyer). Применяет скидку к корзине при 0 оплаченных заказов пользователя.',
        'events' => [
            'OnWebPageInit' => ['priority' => 0],
            'OnWebPagePrerender' => ['priority' => 0],
            'msOnGetCartCost' => ['priority' => 5],
        ],
    ],
];
