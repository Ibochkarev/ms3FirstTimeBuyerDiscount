<?php

/**
 * Resolver для отправки обезличенной статистики установки/обновления.
 *
 * Не собирает персональные данные, домены, IP или конфиденциальную информацию.
 */

use MODX\Revolution\modContext;
use MODX\Revolution\Transport\modTransportPackage;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var \MODX\Revolution\modX $modx */

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return true;
}

$modx = $transport->xpdo;

$allowedActions = [
    xPDOTransport::ACTION_INSTALL,
    xPDOTransport::ACTION_UPGRADE,
];
if (!in_array($options[xPDOTransport::PACKAGE_ACTION] ?? null, $allowedActions, true)) {
    return true;
}

$collectMetrics = function () use ($modx, $options, $transport): array {
    $packageName = 'ms3FirstTimeBuyerDiscount';
    $installType = 'fresh';
    $previousVersion = null;

    $existing = $modx->getObject(modTransportPackage::class, [
        'package_name' => $packageName,
        'installed:IS NOT' => null,
    ]);
    if ($existing) {
        $installType = 'upgrade';
        $previousVersion = (string) $existing->get('version_major') . '.'
            . $existing->get('version_minor') . '.'
            . $existing->get('version_patch');
        $release = $existing->get('release');
        if ($release) {
            $previousVersion .= '-' . $release;
            $idx = $existing->get('release_index');
            if ($idx !== null && $idx !== '') {
                $previousVersion .= $idx;
            }
        }
    }

    $modx->getVersionData();
    $modxVersion = $modx->version['full_version'] ?? 'unknown';
    $contextCount = $modx->getCount(modContext::class);
    $modxLocale = $modx->getOption('cultureKey', null, 'en');

    $ms3Version = null;
    $ms3Package = $modx->getObject(modTransportPackage::class, [
        'package_name' => 'MiniShop3',
        'installed:IS NOT' => null,
    ]);
    if ($ms3Package) {
        $ms3Version = (string) $ms3Package->get('version_major') . '.'
            . $ms3Package->get('version_minor') . '.'
            . $ms3Package->get('version_patch');
        $r = $ms3Package->get('release');
        if ($r) {
            $ms3Version .= '-' . $r;
        }
    }

    $dbType = null;
    $dbVersion = null;
    try {
        $pdo = $modx->getConnection()->pdo ?? null;
        if ($pdo instanceof PDO) {
            $dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $dbVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        }
    } catch (Throwable $e) {
        // ignore
    }

    $webServer = null;
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
    if (stripos($serverSoftware, 'apache') !== false) {
        $webServer = 'Apache';
    } elseif (stripos($serverSoftware, 'nginx') !== false) {
        $webServer = 'Nginx';
    } elseif (stripos($serverSoftware, 'litespeed') !== false) {
        $webServer = 'LiteSpeed';
    } elseif (!empty($serverSoftware)) {
        $webServer = explode('/', $serverSoftware)[0];
    }

    $packageVersion = 'unknown';
    if (!empty($transport->signature)) {
        $parts = explode('-', $transport->signature, 2);
        if (isset($parts[1])) {
            $packageVersion = $parts[1];
        }
    }

    return [
        'package_name' => $packageName,
        'package_version' => $packageVersion,
        'install_type' => $installType,
        'previous_version' => $previousVersion,
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'modx_version' => $modxVersion,
        'modx_locale' => $modxLocale,
        'modx_context_count' => $contextCount,
        'minishop3_version' => $ms3Version,
        'db_type' => $dbType,
        'db_version' => $dbVersion,
        'os_type' => PHP_OS_FAMILY,
        'web_server' => $webServer,
    ];
};

$sendMetrics = function (array $metrics): bool {
    $url = 'https://metrics.modx.pro/';
    $payload = json_encode($metrics, JSON_UNESCAPED_UNICODE);
    $timeout = 2;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $ok = curl_errno($ch) === 0;
        curl_close($ch);
        return $ok;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        @file_get_contents($url, false, $ctx);
        return true;
    }

    return false;
};

try {
    $metrics = $collectMetrics();
    $sendMetrics($metrics);
} catch (Throwable $e) {
    // не прерываем установку
}

return true;
