<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$stores = cms_store_repository()->published();
$regions = [];
foreach ($stores as $store) {
    $region = (string) ($store['prefecture'] ?: 'その他');
    $regions[$region][] = $store;
}
$typeLabels = ['direct' => '直営店', 'fc' => 'FC店', 'office' => '営業所', 'warehouse' => '倉庫・拠点'];
$consultationUrl = (string) app_config()['consultation_url'];
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>店舗を探す | プロ厨房HIT</title><meta name="description" content="厨房機器の購入から厨房づくり、開業準備まで相談できるプロ厨房HITの店舗一覧です。">
<link rel="stylesheet" href="assets/catalogue.css"><link rel="stylesheet" href="assets/store.css"></head><body>
<header class="public-header"><a class="public-brand" href="index.php"><small>厨房づくりと厨房機器</small><strong>プロ厨房HIT</strong></a><nav><a href="index.php">ホーム</a><a href="stores.php" aria-current="page">店舗を探す</a></nav></header>
<main><section class="store-hero"><div><p class="store-eyebrow">SHOP NETWORK</p><h1>厨房づくりを相談できる<br>地域のプロを探す。</h1><p>厨房機器を選ぶだけでなく、レイアウト、搬入・設置、開業準備まで。各地域のプロ厨房HITが、店舗づくりを一緒に考えます。</p></div><aside><strong><?= count($stores) ?></strong><span>掲載店舗・拠点</span></aside></section>
<nav class="region-nav" aria-label="地域から探す"><?php foreach (array_keys($regions) as $region): ?><a href="#region-<?= h(rawurlencode($region)) ?>"><?= h($region) ?></a><?php endforeach; ?></nav>
<section class="store-directory">
<?php if ($stores === []): ?><div class="store-empty"><h2>店舗情報を準備しています</h2><p>公開までしばらくお待ちください。</p></div><?php endif; ?>
<?php foreach ($regions as $region => $regionStores): ?><section class="region-section" id="region-<?= h(rawurlencode($region)) ?>"><header><p>AREA</p><h2><?= h($region) ?></h2><span><?= count($regionStores) ?>拠点</span></header><div class="store-cards">
<?php foreach ($regionStores as $store): ?><article class="store-card"><a class="store-card-image" href="store.php?slug=<?= rawurlencode($store['slug']) ?>"><?php if ($store['main_image'] !== ''): ?><img src="<?= h(public_url($store['main_image'])) ?>" alt="<?= h($store['name']) ?>"><?php else: ?><span>NO IMAGE</span><?php endif; ?><em><?= h($typeLabels[$store['store_type']] ?? $store['store_type']) ?></em></a><div class="store-card-copy"><p class="store-location"><?= h($store['prefecture'] . $store['city']) ?></p><h3><a href="store.php?slug=<?= rawurlencode($store['slug']) ?>"><?= h($store['name']) ?></a></h3><?php if ($store['catchphrase'] !== ''): ?><p class="store-catch"><?= h($store['catchphrase']) ?></p><?php endif; ?><dl><div><dt>営業時間</dt><dd><?= h($store['business_hours'] ?: '店舗へお問い合わせください') ?></dd></div><div><dt>定休日</dt><dd><?= h($store['holidays'] ?: '店舗へお問い合わせください') ?></dd></div></dl><div class="store-card-actions"><a href="store.php?slug=<?= rawurlencode($store['slug']) ?>">店舗詳細</a><?php if ($store['phone'] !== ''): ?><a class="tel" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $store['phone'])) ?>">電話する</a><?php endif; ?></div></div></article><?php endforeach; ?>
</div></section><?php endforeach; ?></section>
<section class="store-consult"><p class="store-eyebrow">OPENING SUPPORT</p><h2>どの店舗に相談すればよいか<br>分からない方へ</h2><p>開業予定地やお探しの機器を伺い、相談先をご案内します。</p><?php if ($consultationUrl !== ''): ?><a href="<?= h($consultationUrl) ?>">厨房づくりを相談する</a><?php endif; ?></section></main>
<footer class="public-footer"><strong>プロ厨房HIT</strong><small>株式会社アイリテクノ</small></footer></body></html>
