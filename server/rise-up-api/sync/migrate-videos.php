<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);

$domainRoot = dirname(__DIR__);
$configPath = $domainRoot . '/api-config.php';
if (!is_file($configPath)) { fwrite(STDERR, "API configuration was not found.\n"); exit(2); }
$config = require $configPath;
if (!is_array($config)) { fwrite(STDERR, "API configuration is invalid.\n"); exit(2); }

$copy = in_array('--copy', $argv, true);
$source = rtrim(isset($config['legacy_video_directory']) ? (string)$config['legacy_video_directory'] : dirname($domainRoot) . '/pro-chubo.com/public_html/img_item_movie', '/');
$target = rtrim(isset($config['video_directory']) ? (string)$config['video_directory'] : $domainRoot . '/public_html/rubs/img/item_movie', '/');
if (!is_dir($source)) { fwrite(STDERR, "Legacy video directory was not found.\n"); exit(2); }
if ($copy && !is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) { fwrite(STDERR, "Video destination could not be created.\n"); exit(2); }

$files = glob($source . '/*.mp4');
if (!is_array($files)) { $files = array(); }
$summary = array('mode'=>$copy?'copy':'dry-run','found'=>count($files),'copied'=>0,'existing'=>0,'invalid'=>0,'failed'=>0);
foreach ($files as $sourcePath) {
    $name = basename($sourcePath);
    if (!preg_match('/^[0-9]{13}\.mp4$/', $name) || !is_file($sourcePath)) { $summary['invalid']++; continue; }
    $targetPath = $target . '/' . $name;
    if (is_file($targetPath)) {
        if (filesize($targetPath) !== filesize($sourcePath)) { $summary['failed']++; fwrite(STDERR, "Size mismatch: " . $name . "\n"); }
        else { $summary['existing']++; }
        continue;
    }
    if (!$copy) { continue; }
    $temporary = $targetPath . '.tmp-' . getmypid();
    if (!copy($sourcePath, $temporary) || filesize($temporary) !== filesize($sourcePath) || !rename($temporary, $targetPath)) {
        @unlink($temporary); $summary['failed']++; fwrite(STDERR, "Copy failed: " . $name . "\n"); continue;
    }
    chmod($targetPath, 0644);
    $summary['copied']++;
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($summary['failed'] > 0 ? 1 : 0);
