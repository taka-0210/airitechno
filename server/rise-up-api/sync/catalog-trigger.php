<?php

/**
 * Run the catalogue delta sync after 厨房君 finishes its HP掲載処理.
 *
 * This helper is intentionally independent from the 厨房君 codebase so that
 * the integration there remains a small require_once + function call.
 *
 * @return array{ok: bool, message: string}
 */
function trigger_catalog_sync_now()
{
    $syncScript = __DIR__ . '/sync-catalog.php';
    $domainRoot = dirname(__DIR__);
    $logDirectory = $domainRoot . '/log/api-sync';
    $logPath = $logDirectory . '/catalog-sync.log';

    if (!is_file($syncScript)) {
        error_log('[catalog-sync] Sync script was not found: ' . $syncScript);
        return array('ok' => false, 'message' => 'sync script was not found');
    }

    if (!function_exists('exec')) {
        error_log('[catalog-sync] PHP exec() is unavailable.');
        return array('ok' => false, 'message' => 'exec is unavailable');
    }

    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    $output = array();
    $exitCode = 1;
    $command = '/usr/bin/php7.4 ' . escapeshellarg($syncScript) . ' delta 2>&1';
    exec($command, $output, $exitCode);

    $logLine = '[' . date('c') . '] source=hp-publish exit=' . $exitCode;
    if ($output !== array()) {
        $logLine .= ' output=' . implode(' ', $output);
    }
    @file_put_contents($logPath, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);

    if ($exitCode !== 0) {
        error_log('[catalog-sync] Immediate sync failed: ' . implode(' ', $output));
        return array('ok' => false, 'message' => 'sync command failed');
    }

    return array('ok' => true, 'message' => 'catalogue synchronized');
}

