<?php

/** @var modX $modx */
/** @var array $scriptProperties */

if (($modx->event->name ?? '') !== 'msOnGetCartCost') {
    return;
}

$corePath = $modx->getOption('core_path');
$bootstrap = $corePath . 'components/ms3firsttimebuyerdiscount/bootstrap.php';
if (file_exists($bootstrap)) {
    $namespace = ['path' => $corePath . 'components/ms3firsttimebuyerdiscount/'];
    require_once $bootstrap;
}

try {
    $service = $modx->services->get('ms3ftb_discount');
} catch (Throwable $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[FTB] Service not available: ' . $e->getMessage(), '', 'ftb', __FILE__, __LINE__);
    return;
}

$newCost = $service->apply($scriptProperties);
if ($newCost === null) {
    return;
}

$modx->event->returnedValues['cost'] = $newCost;
$costBefore = (float) ($scriptProperties['cost'] ?? 0);
$discountAmount = $costBefore - $newCost;
$userId = $scriptProperties['draft'] && is_object($scriptProperties['draft'])
    ? $scriptProperties['draft']->get('user_id')
    : $modx->user->get('id');
$modx->log(
    modX::LOG_LEVEL_INFO,
    sprintf('[FTB] Applied: user_id=%s, discount=%s, cost_before=%s, cost_after=%s', $userId, $discountAmount, $costBefore, $newCost),
    '',
    'ftb',
    __FILE__,
    __LINE__
);
