<?php
declare(strict_types=1);

define('AIRITECHNO_ROOT', dirname(__DIR__));

require_once AIRITECHNO_ROOT . '/src/ProChuboApiClient.php';
require_once AIRITECHNO_ROOT . '/src/ProductRepository.php';
require_once AIRITECHNO_ROOT . '/src/CmsDatabase.php';
require_once AIRITECHNO_ROOT . '/src/CmsAuth.php';
require_once AIRITECHNO_ROOT . '/src/CmsStoreRepository.php';

function env_value(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function app_config(): array
{
    $config = [
        'api_base_url' => rtrim((string) env_value('PRO_CHUBO_API_BASE_URL', ''), '/'),
        'api_key' => (string) env_value('PRO_CHUBO_API_KEY', ''),
        'store_id' => (string) env_value('PRO_CHUBO_STORE_ID', '265'),
        'cache_ttl' => max(30, (int) env_value('PRO_CHUBO_CACHE_TTL', '300')),
        'contact_url' => (string) env_value('AIRITECHNO_CONTACT_URL', '/contact/?product_id={product_id}'),
        'cms_database_path' => (string) env_value('AIRITECHNO_CMS_DATABASE_PATH', AIRITECHNO_ROOT . '/storage/cms/cms.sqlite'),
        'public_base_url' => rtrim((string) env_value('AIRITECHNO_PUBLIC_BASE_URL', '/airitechno/public'), '/'),
        'reservation_url' => (string) env_value('AIRITECHNO_RESERVATION_URL', ''),
    ];
    $localPath = AIRITECHNO_ROOT . '/config/local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (is_array($local)) {
            $config = array_replace($config, $local);
        }
    }
    $config['api_base_url'] = rtrim((string) $config['api_base_url'], '/');
    $config['store_id'] = (string) $config['store_id'];
    $config['cache_ttl'] = max(30, (int) $config['cache_ttl']);
    return $config;
}

function cms_database(): CmsDatabase
{
    static $database;
    if (!$database instanceof CmsDatabase) {
        $database = new CmsDatabase((string) app_config()['cms_database_path']);
    }
    return $database;
}

function cms_store_repository(): CmsStoreRepository
{
    return new CmsStoreRepository(cms_database()->pdo());
}

function public_url(string $path = ''): string
{
    return (string) app_config()['public_base_url'] . '/' . ltrim($path, '/');
}

function product_repository(): ProductRepository
{
    $config = app_config();
    $client = null;
    if ($config['api_base_url'] !== '' && $config['api_key'] !== '') {
        $client = new ProChuboApiClient($config['api_base_url'], $config['api_key']);
    }

    return new ProductRepository(
        $client,
        $config['store_id'],
        AIRITECHNO_ROOT . '/storage/cache/products-' . $config['store_id'] . '.json',
        AIRITECHNO_ROOT . '/data/products.sample.json',
        $config['cache_ttl']
    );
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function product_status_label(string $status): string
{
    return match ($status) {
        'available' => '販売中',
        'negotiating' => '商談中',
        'reserved' => '売約済み',
        'shipped' => '出荷済み',
        default => $status !== '' ? $status : '在庫確認中',
    };
}

function product_contact_url(array $product): string
{
    $template = app_config()['contact_url'];
    return str_replace('{product_id}', rawurlencode((string) ($product['id'] ?? '')), $template);
}
