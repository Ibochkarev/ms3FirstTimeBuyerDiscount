<?php

/**
 * Bootstrap ms3FirstTimeBuyerDiscount.
 * Вызывается при загрузке namespace или из плагина.
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $namespace (опционально, при загрузке через namespace)
 */

$componentPath = $namespace['path'] ?? MODX_CORE_PATH . 'components/ms3firsttimebuyerdiscount/';
$autoload = $componentPath . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!$modx->services->has('ms3ftb_discount')) {
    $modx->services->add('ms3ftb_discount', fn () => new \Ms3FirstTimeBuyerDiscount\Services\FtbDiscountService($modx));
}
