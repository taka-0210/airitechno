<?php

define('PRO_CHUBO_PUBLIC_ROOT', dirname(dirname(dirname(__DIR__))));
define('PRO_CHUBO_DOMAIN_ROOT', dirname(PRO_CHUBO_PUBLIC_ROOT));

$apiConfigPath = PRO_CHUBO_DOMAIN_ROOT . '/api-config.php';
if (!is_file($apiConfigPath)) {
    api_error(503, 'API is not configured.');
}
$apiConfig = require $apiConfigPath;
if (!is_array($apiConfig)) {
    api_error(503, 'API configuration is invalid.');
}

ob_start();
require_once PRO_CHUBO_PUBLIC_ROOT . '/cmn/db.php';
ob_end_clean();

function api_config($key, $default)
{
    global $apiConfig;
    return isset($apiConfig[$key]) ? $apiConfig[$key] : $default;
}

function api_db()
{
    static $db = null;
    if ($db === null) {
        $db = new PDO(PDO_DNS, PDO_USER, PDO_PASSWORD);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $db;
}

function api_headers()
{
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    if (!is_array($headers)) {
        $headers = array();
    }
    $normalised = array();
    foreach ($headers as $name => $value) {
        $normalised[strtolower($name)] = $value;
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $normalised['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $normalised['x-api-key'] = $_SERVER['HTTP_X_API_KEY'];
    }
    return $normalised;
}

function api_secure_equals($known, $given)
{
    if (!is_string($known) || !is_string($given) || strlen($known) !== strlen($given)) {
        return false;
    }
    $difference = 0;
    for ($index = 0, $length = strlen($known); $index < $length; $index++) {
        $difference |= ord($known[$index]) ^ ord($given[$index]);
    }
    return $difference === 0;
}

function api_authenticate()
{
    $headers = api_headers();
    $key = isset($headers['x-api-key']) ? trim($headers['x-api-key']) : '';
    if ($key === '' && isset($headers['authorization']) && preg_match('/^Bearer\s+(.+)$/i', trim($headers['authorization']), $matches)) {
        $key = trim($matches[1]);
    }
    if ($key === '') {
        api_error(401, 'API key is required.');
    }

    $givenHash = hash('sha256', $key);
    foreach ((array) api_config('api_keys', array()) as $knownHash => $permissions) {
        if (api_secure_equals(strtolower($knownHash), strtolower($givenHash))) {
            return is_array($permissions) ? $permissions : array();
        }
    }
    api_error(401, 'API key is invalid.');
}

function api_authorise_store($permissions, $storeId)
{
    $stores = isset($permissions['stores']) ? array_map('strval', (array) $permissions['stores']) : array();
    if (!in_array((string) $storeId, $stores, true)) {
        api_error(403, 'This API key cannot access the requested store.');
    }
}

function api_authorise_catalog($permissions)
{
    if (empty($permissions['all_stores'])) {
        api_error(403, 'This API key cannot access the full catalog.');
    }
}

function api_utf8($value)
{
    if (is_array($value)) {
        $converted = array();
        foreach ($value as $key => $item) {
            $converted[$key] = api_utf8($item);
        }
        return $converted;
    }
    if (!is_string($value) || $value === '') {
        return $value;
    }
    return mb_convert_encoding($value, 'UTF-8', 'EUC-JP');
}

function api_euc($value)
{
    return mb_convert_encoding((string) $value, 'EUC-JP', 'UTF-8');
}

function api_json($status, $payload, $cacheSeconds)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        api_error(500, 'JSON encoding failed.');
    }
    $etag = '"' . sha1($json) . '"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        exit;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, max-age=' . max(0, (int) $cacheSeconds));
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

function api_error($status, $message)
{
    api_json($status, array('error' => array('status' => $status, 'message' => $message)), 0);
}

function api_status_code($status)
{
    if ($status === '販売中') {
        return 'available';
    }
    if ($status === '商談中') {
        return 'negotiating';
    }
    if ($status === '売約済み' || $status === '売約済') {
        return 'reserved';
    }
    if ($status === '出荷済み' || $status === '販売済み') {
        return 'shipped';
    }
    return 'unavailable';
}

function api_product_images($jancode)
{
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        return array();
    }
    $files = glob(PRO_CHUBO_PUBLIC_ROOT . '/img_item/' . $jancode . '-*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
    if (!is_array($files)) {
        return array();
    }
    natsort($files);
    $images = array();
    $thumbnailBase = rtrim(api_config('thumbnail_base_url', ''), '/');
    $imageBase = rtrim(api_config('image_base_url', 'https://pro-chubo.com/img_item'), '/');
    foreach (array_values($files) as $file) {
        $filename = basename($file);
        if (!preg_match('/^' . preg_quote($jancode, '/') . '-([0-9]+)\.(jpe?g|png|webp)$/i', $filename, $matches)) {
            continue;
        }
        $number = (int) $matches[1];
        $images[] = array(
            'url' => $imageBase . '/' . rawurlencode($filename),
            'thumbnail_url' => $thumbnailBase . '?id=' . rawurlencode($jancode) . '&no=' . $number . '&size=480',
            'sort_order' => $number,
        );
    }
    return $images;
}

function api_product_primary_image($jancode)
{
    if (!preg_match('/^[0-9]{13}$/', $jancode)) {
        return array();
    }
    $imageBase = rtrim(api_config('image_base_url', 'https://pro-chubo.com/img_item'), '/');
    $thumbnailBase = rtrim(api_config('thumbnail_base_url', ''), '/');
    return array(array(
        'url' => $imageBase . '/' . rawurlencode($jancode . '-0.jpg'),
        'thumbnail_url' => $thumbnailBase . '?id=' . rawurlencode($jancode) . '&no=0&size=480',
        'sort_order' => 0,
    ));
}

function api_normalise_product($row, $includeDescription)
{
    $row = api_utf8($row);
    $jancode = (string) $row['jan13'];
    $status = (string) $row['status'];
    $product = array(
        'id' => $jancode,
        'name' => trim((string) $row['shohin']),
        'manufacturer' => trim((string) $row['maker']),
        'model_number' => trim((string) $row['model']),
        'year' => (int) $row['year'],
        'condition' => array('code' => trim((string) $row['rank']), 'label' => trim((string) $row['rank']) === '' ? '' : 'ランク' . trim((string) $row['rank'])),
        'dimensions_mm' => array('width' => (int) $row['w'], 'depth' => (int) $row['d'], 'height' => (int) $row['h']),
        'price' => array(
            'tax_excluded' => (int) $row['price2_notax'],
            'tax_included' => (int) $row['price2'],
            'currency' => 'JPY',
            'ask' => !empty($row['ask']),
        ),
        'inventory' => array(
            'status' => api_status_code($status),
            'status_label' => $status,
            'store_id' => (string) $row['shop_id'],
            'store_name' => api_store_name((string) $row['shop_id']),
            'quantity' => (int) $row['stock'],
            'shipped_at' => trim((string) $row['date_out']),
        ),
        'category' => array(
            'class1' => trim((string) $row['class1']),
            'class2_id' => (int) $row['class2_id'],
            'class2' => trim((string) $row['class2']),
        ),
        'images' => $includeDescription ? api_product_images($jancode) : api_product_primary_image($jancode),
        'videos' => array(),
        'listed_at' => trim((string) $row['date']),
    );
    if ($includeDescription) {
        $description = trim((string) $row['tokucho']);
        $comment = trim((string) $row['comment']);
        $product['description'] = $description;
        $product['remarks'] = $comment;
        $product['warranty'] = trim((string) $row['hosyo']);
    }
    return $product;
}

function api_store_name($storeId)
{
    static $stores = null;
    if ($stores === null) {
        $stores = array();
        $statement = api_db()->query('SELECT shop_id, shop FROM shop_tb WHERE status = 1');
        foreach ($statement->fetchAll() as $row) {
            $row = api_utf8($row);
            $stores[(string) $row['shop_id']] = trim((string) $row['shop']);
        }
    }
    return isset($stores[(string) $storeId]) ? $stores[(string) $storeId] : '店舗ID ' . $storeId;
}

function api_public_where($storeId, &$parameters)
{
    $parameters[':store_id'] = (string) $storeId;
    $parameters[':public_status'] = api_euc('公開在庫');
    return 'shop_id = :store_id AND status2 = :public_status AND COALESCE(flag_close, 0) = 0';
}

function api_public_catalog_where(&$parameters)
{
    $parameters[':public_status'] = api_euc('公開在庫');
    return 'status2 = :public_status AND COALESCE(flag_close, 0) = 0';
}
