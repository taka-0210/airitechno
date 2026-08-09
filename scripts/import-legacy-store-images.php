<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$sourceDirectory = rtrim((string) ($argv[1] ?? ''), '/\\');
if ($sourceDirectory === '' || !is_dir($sourceDirectory)) {
    fwrite(STDERR, "Usage: php scripts/import-legacy-store-images.php path/to/images\n");
    exit(1);
}

$repository = cms_store_repository();
$stores = [];
foreach ($repository->all() as $store) {
    $stores[(string) $store['kitchen_store_id']] = $store;
}

$created = 0;
$skipped = 0;
$files = glob($sourceDirectory . DIRECTORY_SEPARATOR . '*.jpg') ?: [];
sort($files, SORT_NATURAL);
foreach ($files as $sourcePath) {
    if (!preg_match('/^(\d+)-(\d+)\.jpg$/', basename($sourcePath), $matches)) {
        $skipped++;
        continue;
    }
    $legacyStoreId = $matches[1];
    $sortOrder = (int) $matches[2];
    if ($sortOrder < 1 || $sortOrder > 8) {
        $skipped++;
        continue;
    }
    $store = $stores[$legacyStoreId] ?? null;
    if ($store === null || @getimagesize($sourcePath) === false) {
        $skipped++;
        continue;
    }
    $sourceUrl = 'https://pro-chubo.com/shop_data/' . basename($sourcePath);
    $exists = array_filter($repository->images((int) $store['id']), static fn(array $image): bool => $image['source_url'] === $sourceUrl);
    if ($exists !== []) {
        $skipped++;
        continue;
    }

    $relativeDirectory = 'uploads/stores/' . (int) $store['id'];
    $targetDirectory = dirname(__DIR__) . '/public/' . $relativeDirectory;
    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('画像保存先を作成できません。');
    }
    $relativePath = $relativeDirectory . '/' . bin2hex(random_bytes(16)) . '.jpg';
    if (!copy($sourcePath, dirname(__DIR__) . '/public/' . $relativePath)) {
        throw new RuntimeException('画像をコピーできません。');
    }
    $repository->addImage((int) $store['id'], $relativePath, (string) $store['name'], $sortOrder, $sourceUrl);
    if ($sortOrder === 1 && (string) $store['main_image'] === '') {
        $store['main_image'] = $relativePath;
        $repository->save($store, (int) $store['id']);
        $stores[$legacyStoreId] = $repository->find((int) $store['id']);
    }
    $created++;
}

echo json_encode(['created' => $created, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE) . PHP_EOL;
