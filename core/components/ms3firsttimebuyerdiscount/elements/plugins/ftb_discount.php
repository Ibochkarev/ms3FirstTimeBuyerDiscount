<?php

/** @var modX $modx */
/** @var array $scriptProperties */

if (!function_exists('ms3ftbRegisterAssets')) {
    /**
     * Register component frontend runtime: CSS and JS from public assets or core fallback.
     */
    function ms3ftbRegisterAssets(modX $modx): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        if (($modx->context->get('key') ?? '') === 'mgr') {
            return;
        }

        $assetsPath = MODX_ASSETS_PATH . 'components/ms3firsttimebuyerdiscount/';
        $assetsUrl = $modx->getOption('assets_url') . 'components/ms3firsttimebuyerdiscount/';
        $corePath = MODX_CORE_PATH . 'components/ms3firsttimebuyerdiscount/assets/';

        $cssPublicDir = $assetsPath . 'css/';
        $cssPublicFiles = is_dir($cssPublicDir) ? (glob($cssPublicDir . '*.css') ?: []) : [];
        if (!empty($cssPublicFiles)) {
            sort($cssPublicFiles);
            foreach ($cssPublicFiles as $fullPath) {
                $fileName = basename($fullPath);
                $version = (string) @filemtime($fullPath);
                $url = $assetsUrl . 'css/' . $fileName;
                if ($version !== '' && $version !== '0') {
                    $url .= '?v=' . rawurlencode($version);
                }
                $modx->regClientCSS($url);
            }
        } else {
            $cssCoreDir = $corePath . 'css/';
            $cssCoreFiles = is_dir($cssCoreDir) ? (glob($cssCoreDir . '*.css') ?: []) : [];
            sort($cssCoreFiles);
            foreach ($cssCoreFiles as $fullPath) {
                $content = @file_get_contents($fullPath);
                if (is_string($content) && $content !== '') {
                    $modx->regClientStartupHTMLBlock('<style id="ms3ftb-inline-css">' . "\n" . $content . "\n" . '</style>');
                }
            }
        }

        $eligibleUrl = $modx->getOption('assets_url') . 'components/ms3firsttimebuyerdiscount/eligible.php';
        $modx->regClientStartupHTMLBlock(
            '<script>window.MS3FTB_ELIGIBLE_URL=' . json_encode($eligibleUrl, JSON_UNESCAPED_SLASHES) . ';</script>'
        );

        $jsPublicFile = $assetsPath . 'js/ftb-order.js';
        $jsCoreFile = $corePath . 'js/ftb-order.js';
        if (is_file($jsPublicFile)) {
            $version = (string) @filemtime($jsPublicFile);
            $url = $assetsUrl . 'js/ftb-order.js';
            if ($version !== '' && $version !== '0') {
                $url .= '?v=' . rawurlencode($version);
            }
            $modx->regClientScript($url);
        } elseif (is_file($jsCoreFile)) {
            $content = @file_get_contents($jsCoreFile);
            if (is_string($content) && $content !== '') {
                $modx->regClientStartupHTMLBlock('<script id="ms3ftb-inline-js">' . "\n" . $content . "\n" . '</script>');
            }
        }

        $registered = true;
    }
}

$event = $modx->event->name ?? '';

if ($event === 'OnWebPageInit' || $event === 'OnWebPagePrerender') {
    ms3ftbRegisterAssets($modx);
    return;
}

if ($event !== 'msOnGetCartCost') {
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
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[FTB] Service not available: ' . $e->getMessage(),
        '',
        'ftb',
        __FILE__,
        __LINE__
    );
    return;
}

if (!is_object($service) || !method_exists($service, 'apply')) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[FTB] Service ms3ftb_discount has invalid implementation', '', 'ftb', __FILE__, __LINE__);
    return;
}

$properties = is_array($scriptProperties ?? null) ? $scriptProperties : [];

try {
    $newCost = $service->apply($properties);
} catch (Throwable $e) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[FTB] Apply failed: ' . $e->getMessage(),
        '',
        'ftb',
        __FILE__,
        __LINE__
    );
    return;
}
if ($newCost === null) {
    return;
}

$returnedValues = $modx->event->returnedValues ?? null;
if (!is_array($returnedValues)) {
    $modx->event->returnedValues = [];
}

$modx->event->returnedValues['cost'] = $newCost;
$costBefore = (float) ($properties['cost'] ?? 0);
$discountAmount = $costBefore - $newCost;
$modx->lexicon->load('ms3firsttimebuyerdiscount:default');
$message = $modx->lexicon('ftb_discount_applied', [
    'amount' => number_format((float) $discountAmount, 0, '.', ' '),
]);
$modx->event->returnedValues['ftb_discount'] = [
    'amount' => $discountAmount,
    'message' => $message,
];
$draft = $properties['draft'] ?? null;
$userId = $draft !== null && is_object($draft) && method_exists($draft, 'get')
    ? $draft->get('user_id')
    : $modx->user->get('id');
$msg = sprintf(
    '[FTB] Applied: user_id=%s, discount=%s, cost_before=%s, cost_after=%s',
    $userId,
    $discountAmount,
    $costBefore,
    $newCost
);
$modx->log(modX::LOG_LEVEL_INFO, $msg, '', 'ftb', __FILE__, __LINE__);
