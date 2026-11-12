<?php

/**
 * Плашка «Скидка на первый заказ» в виде чека.
 * Показывается на странице оформления заказа, если пользователь подходит под условия (0 оплаченных заказов).
 * Для гостей выводится оптимистично (без email/phone проверка невозможна).
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$corePath = $modx->getOption('core_path');
$bootstrap = $corePath . 'components/ms3firsttimebuyerdiscount/bootstrap.php';
if (!file_exists($bootstrap)) {
    return '';
}
$namespace = ['path' => $corePath . 'components/ms3firsttimebuyerdiscount/'];
require_once $bootstrap;

try {
    $service = $modx->services->get('ms3ftb_discount');
} catch (Throwable $e) {
    return '';
}
if (!$service || !method_exists($service, 'isEligible') || !method_exists($service, 'getPaidOrdersCount')) {
    return '';
}

$enabled = (bool) $modx->getOption('ms3firsttimebuyerdiscount_ftb_enabled', null, true);
if (!$enabled) {
    return '';
}

$userId = (int) $modx->user->get('id');
$showBanner = false;
if ($userId > 0) {
    $showBanner = $service->isEligible($userId, null);
} else {
    $showBanner = true;
}
if (!$showBanner) {
    return '';
}

$discountType = strtolower(trim((string) $modx->getOption(
    'ms3firsttimebuyerdiscount_ftb_discount_type',
    null,
    'percent'
)));
$discountValue = (float) $modx->getOption('ms3firsttimebuyerdiscount_ftb_discount_value', null, 10);
$currency = $modx->getOption('ms3_currency', null, '₽');
if (!is_string($currency) || $currency === '') {
    $currency = '₽';
}

$modx->lexicon->load('ms3firsttimebuyerdiscount:default');
$message = $modx->lexicon('ftb_discount_banner_title');
$savingsLabel = $modx->lexicon('ftb_discount_banner_savings_label');
$hint = $modx->lexicon('ftb_discount_banner_hint');
$amountRaw = '';

if ($discountType === 'fixed') {
    $amountRaw = number_format((float) $discountValue, 0, '.', ' ') . ' ' . $currency;
} else {
    $amountRaw = 'до ' . (int) $discountValue . '%';
}

$chunkPath = $corePath . 'components/ms3firsttimebuyerdiscount/elements/chunks/ms3ftb_discount_banner.tpl';
if (file_exists($chunkPath)) {
    $tpl = (string) file_get_contents($chunkPath);
} else {
    $tpl = '<div id="ms3ftb-discount-banner" class="ms3ftb-banner" role="status">'
        . '<span class="ms3ftb-banner__content">[[+message]]. [[+savings_label]]: [[+amount_raw]]. [[+hint]]</span></div>';
}

$placeholders = [
    '[[+message]]' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    '[[+savings_label]]' => htmlspecialchars($savingsLabel, ENT_QUOTES, 'UTF-8'),
    '[[+amount_raw]]' => htmlspecialchars($amountRaw, ENT_QUOTES, 'UTF-8'),
    '[[+hint]]' => htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'),
];
$output = str_replace(array_keys($placeholders), array_values($placeholders), $tpl);

return $output;
