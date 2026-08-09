<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$sourcePath = (string) ($argv[1] ?? '');
if ($sourcePath === '' || !is_file($sourcePath)) {
    fwrite(STDERR, "Usage: php scripts/import-legacy-stores.php path/to/legacy-stores.json\n");
    exit(1);
}

$source = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
$stores = is_array($source['stores'] ?? null) ? $source['stores'] : [];
$repository = cms_store_repository();
$existing = [];
foreach ($repository->all() as $store) {
    if ((string) $store['kitchen_store_id'] !== '') {
        $existing[(string) $store['kitchen_store_id']] = $store;
    }
}

$slugs = [
    '271' => 'head-office-reuse-center',
    '283' => 'himeji',
    '295' => 'harima',
    '273' => 'takamatsu',
    '219' => 'okayama',
    '281' => 'awajishima',
    '261' => 'okinawa',
    '285' => 'kakogawa-warehouse',
    '265' => 'niihama',
];
$types = ['0' => 'direct', '1' => 'fc'];
$created = 0;
$updated = 0;
$skipped = 0;

foreach ($stores as $legacy) {
    $legacyId = (string) ($legacy['shop_id'] ?? '');
    if ((string) ($legacy['status'] ?? '0') !== '1' || !isset($slugs[$legacyId])) {
        $skipped++;
        continue;
    }

    $current = $existing[$legacyId] ?? null;
    $address = trim((string) ($legacy['address'] ?? ''));
    $prefecture = '';
    $cityAndAddress = $address;
    if (preg_match('/^(東京都|北海道|(?:京都|大阪)府|.{2,3}県)(.*)$/u', $address, $matches)) {
        $prefecture = $matches[1];
        $cityAndAddress = trim($matches[2]);
    }

    $input = [
        'kitchen_store_id' => $legacyId,
        'name' => (string) ($legacy['shop'] ?? ''),
        'slug' => $slugs[$legacyId],
        'store_type' => $legacyId === '285' ? 'warehouse' : ($types[(string) ($legacy['type'] ?? '1')] ?? 'fc'),
        'status' => (string) ($current['status'] ?? 'draft'),
        'postal_code' => (string) ($legacy['zip_code'] ?? ''),
        'prefecture' => $prefecture,
        'city' => $cityAndAddress,
        'address_line' => '',
        'building' => '',
        'phone' => (string) ($legacy['tel'] ?? ''),
        'fax' => (string) ($legacy['fax'] ?? ''),
        'email' => (string) ($legacy['mail'] ?? ''),
        'business_hours' => (string) ($legacy['eigyo_jikan'] ?? ''),
        'holidays' => (string) ($legacy['yasumi'] ?? ''),
        'service_area' => (string) ($current['service_area'] ?? ''),
        'catchphrase' => (string) ($legacy['hinmoku'] ?? ''),
        'description' => (string) ($legacy['shokai'] ?? ''),
        'specialties' => (string) ($current['specialties'] ?? ''),
        'services' => (string) ($legacy['hinmoku'] ?? ''),
        'manager_name' => (string) ($current['manager_name'] ?? ''),
        'manager_staff_id' => (string) ($current['manager_staff_id'] ?? ''),
        'map_url' => extract_iframe_url((string) ($legacy['google_map'] ?? '')),
        'line_url' => extract_anchor_url((string) ($legacy['line_btn'] ?? '')),
        'website_url' => (string) ($current['website_url'] ?? ''),
        'main_image' => (string) ($current['main_image'] ?? ''),
        'accepts_reservations' => (int) ($current['accepts_reservations'] ?? 0),
        'reservation_note' => (string) ($current['reservation_note'] ?? ''),
        'sort_order' => (int) ($legacy['sort_order'] ?? 0),
        'published_at' => $current['published_at'] ?? null,
    ];

    $repository->save($input, $current === null ? null : (int) $current['id']);
    $current === null ? $created++ : $updated++;
}

echo json_encode(['created' => $created, 'updated' => $updated, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE) . PHP_EOL;

function extract_iframe_url(string $html): string
{
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $decoded, $matches) ? $matches[1] : '';
}

function extract_anchor_url(string $html): string
{
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_match('/<a[^>]+href=["\']([^"\']+)["\']/i', $decoded, $matches) ? $matches[1] : '';
}
