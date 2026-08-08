<?php

require_once __DIR__ . '/lib/bootstrap.php';

$jancode = isset($_GET['id']) ? (string) $_GET['id'] : '';
$number = isset($_GET['no']) ? (int) $_GET['no'] : 0;
$common = isset($_GET['common']) ? (int) $_GET['common'] : 0;
$size = isset($_GET['size']) ? (int) $_GET['size'] : 480;
$size = max(160, min(960, $size));

if (!preg_match('/^[0-9]{13}$/', $jancode) || $number < 0 || $number > 30) {
    http_response_code(400);
    exit;
}

$sourceRoot = '/home/xsvx1007016/rise-up.net/public_html/rubs/img/' . ($common > 0 ? 'item_cmn/' : 'item/');
$sourceId = $common > 0 ? (string) $common : $jancode;
$matches = glob($sourceRoot . $sourceId . '-' . $number . '.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
if (!is_array($matches) || !isset($matches[0]) || !is_file($matches[0])) {
    http_response_code(404);
    exit;
}
$sourcePath = $matches[0];
$imageInfo = @getimagesize($sourcePath);
if (!$imageInfo || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
    http_response_code(415);
    exit;
}

$cacheRoot = rtrim(api_config('cache_directory', PRO_CHUBO_DOMAIN_ROOT . '/api-cache'), '/');
$cacheDirectory = $cacheRoot . '/thumbnails/' . ($common > 0 ? 'common-' . $common : $jancode);
$cachePath = $cacheDirectory . '/' . $number . '-' . $size . '.jpg';
if (!is_file($cachePath) || filemtime($cachePath) < filemtime($sourcePath)) {
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
        http_response_code(500);
        exit;
    }
    $mime = isset($imageInfo['mime']) ? $imageInfo['mime'] : '';
    if ($mime === 'image/jpeg') {
        $source = @imagecreatefromjpeg($sourcePath);
    } elseif ($mime === 'image/png') {
        $source = @imagecreatefrompng($sourcePath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $source = @imagecreatefromwebp($sourcePath);
    } else {
        $source = false;
    }
    if (!$source) {
        http_response_code(415);
        exit;
    }
    $scale = min(1, $size / max($imageInfo[0], $imageInfo[1]));
    $width = max(1, (int) round($imageInfo[0] * $scale));
    $height = max(1, (int) round($imageInfo[1] * $scale));
    $target = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $imageInfo[0], $imageInfo[1]);
    $temporary = $cachePath . '.tmp-' . getmypid();
    $saved = imagejpeg($target, $temporary, 82);
    imagedestroy($source);
    imagedestroy($target);
    if (!$saved || !rename($temporary, $cachePath)) {
        @unlink($temporary);
        http_response_code(500);
        exit;
    }
}

$etag = '"' . sha1($cachePath . '|' . filemtime($cachePath) . '|' . filesize($cachePath)) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($cachePath));
header('Cache-Control: public, max-age=2592000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
readfile($cachePath);
