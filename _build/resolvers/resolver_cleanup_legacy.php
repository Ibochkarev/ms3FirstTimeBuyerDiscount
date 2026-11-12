<?php
/**
 * Resolver: cleanup of legacy package objects.
 *
 * Removes obsolete "ms3FirstTimeBuyerDiscount Lexicon" plugin that is no longer used.
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

$legacyPlugin = $modx->getObject('modPlugin', ['name' => 'ms3FirstTimeBuyerDiscount Lexicon']);
if ($legacyPlugin) {
    if ($legacyPlugin->remove()) {
        $modx->log(\modX::LOG_LEVEL_INFO, '[ms3FirstTimeBuyerDiscount] Removed legacy plugin: ms3FirstTimeBuyerDiscount Lexicon');
    } else {
        $modx->log(\modX::LOG_LEVEL_ERROR, '[ms3FirstTimeBuyerDiscount] Failed to remove legacy plugin: ms3FirstTimeBuyerDiscount Lexicon');
    }
}

return true;
