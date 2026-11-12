<?php

/**
 * Endpoint проверки права на скидку по email/phone (гости).
 * Вызывается с фронта при изменении email/phone на странице заказа.
 * Возвращает JSON: {"eligible": true|false}
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (file_exists($root . '/core/config/config.inc.php')) {
        break;
    }
    $root = dirname($root);
    if ($root === '/' || $root === '' || dirname($root) === $root) {
        echo json_encode(['eligible' => false, 'error' => 'config']);
        exit;
    }
}

if (!file_exists($root . '/core/config/config.inc.php')) {
    echo json_encode(['eligible' => false, 'error' => 'config']);
    exit;
}

define('MODX_CORE_PATH', $root . '/core/');
require_once MODX_CORE_PATH . 'config/config.inc.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new \MODX\Revolution\modX(MODX_CORE_PATH . 'config/', []);
$modx->initialize('web');

$corePath = MODX_CORE_PATH . 'components/ms3firsttimebuyerdiscount/';
$bootstrap = $corePath . 'bootstrap.php';
if (!file_exists($bootstrap)) {
    echo json_encode(['eligible' => false]);
    exit;
}
$namespace = ['path' => $corePath];
require_once $bootstrap;

try {
    $service = $modx->services->get('ms3ftb_discount');
} catch (Throwable $e) {
    echo json_encode(['eligible' => false]);
    exit;
}

if (!$service || !method_exists($service, 'getPaidOrdersCountByContact')) {
    echo json_encode(['eligible' => false]);
    exit;
}

$enabled = (bool) $modx->getOption('ms3firsttimebuyerdiscount_ftb_enabled', null, true);
if (!$enabled) {
    echo json_encode(['eligible' => false]);
    exit;
}

$email = isset($_REQUEST['email']) ? trim((string) $_REQUEST['email']) : '';
$phone = isset($_REQUEST['phone']) ? trim((string) $_REQUEST['phone']) : '';
if ($email === '' && $phone === '') {
    echo json_encode(['eligible' => false]);
    exit;
}

$count = $service->getPaidOrdersCountByContact($email, $phone, 0);
echo json_encode(['eligible' => $count === 0]);
