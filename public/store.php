<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$slug = (string) ($_GET['slug'] ?? '');
$preview = (string) ($_GET['preview'] ?? '') === '1';
$previewUser = null;
if ($preview) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('airitechno_cms');
        session_start();
    }
    $previewUser = (new CmsAuth(cms_database()->pdo()))->user();
}
$repository = cms_store_repository();
$store = $preview && $previewUser !== null ? $repository->findBySlug($slug) : $repository->findPublishedBySlug($slug);
$storeImages = $store === null ? [] : $repository->images((int) $store['id']);
$reservationUrl = $store === null ? '' : str_replace('{store_slug}', rawurlencode((string) $store['slug']), (string) app_config()['reservation_url']);
$typeLabels = ['direct' => '直営店', 'fc' => 'FC店', 'office' => '営業所', 'warehouse' => '倉庫・拠点'];
if ($store === null) { http_response_code(404); }
$services = $store === null ? [] : preg_split('/[\s　、,]+/u', trim((string) $store['services']), -1, PREG_SPLIT_NO_EMPTY);
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($store['name'] ?? '店舗が見つかりません') ?> | プロ厨房HIT</title><meta name="description" content="<?= h($store['catchphrase'] ?? '') ?>">
<link rel="stylesheet" href="assets/catalogue.css"><link rel="stylesheet" href="assets/store.css"></head><body>
<?php if ($preview && $previewUser !== null): ?><div class="preview-bar">下書きプレビュー中 <a href="admin/stores/edit.php?id=<?= (int) ($store['id'] ?? 0) ?>">編集画面へ戻る</a></div><?php endif; ?>
<header class="public-header"><a class="public-brand" href="index.php"><small>厨房づくりと厨房機器</small><strong>プロ厨房HIT</strong></a><nav><a href="index.php">ホーム</a><a href="stores.php">店舗を探す</a></nav></header>
<main><?php if ($store === null): ?><section class="store-empty"><h1>店舗が見つかりません</h1><p>非公開またはURLが変更された可能性があります。</p><a href="stores.php">店舗一覧へ戻る</a></section><?php else: ?>
<nav class="store-breadcrumb"><a href="index.php">ホーム</a><span>›</span><a href="stores.php">店舗一覧</a><span>›</span><span><?= h($store['name']) ?></span></nav>
<section class="store-detail-head"><div><p class="store-eyebrow"><?= h($typeLabels[$store['store_type']] ?? 'STORE') ?></p><h1><?= h($store['name']) ?></h1><?php if ($store['catchphrase'] !== ''): ?><p><?= h($store['catchphrase']) ?></p><?php endif; ?></div><p class="store-area-label"><?= h($store['prefecture']) ?><br><strong><?= h($store['city']) ?></strong></p></section>
<section class="store-detail-grid"><div class="store-visual"><?php if ($storeImages !== []): ?><div class="store-main-image"><img id="store-main-photo" src="<?= h(public_url($store['main_image'] ?: $storeImages[0]['file_path'])) ?>" alt="<?= h($store['name']) ?>"></div><div class="store-thumbnails"><?php foreach ($storeImages as $image): ?><button type="button" data-image="<?= h(public_url($image['file_path'])) ?>"><img src="<?= h(public_url($image['file_path'])) ?>" alt="<?= h($image['alt_text']) ?>"></button><?php endforeach; ?></div><?php else: ?><div class="store-no-image">店舗写真を準備中です</div><?php endif; ?></div>
<aside class="store-info"><p class="store-info-label">SHOP INFORMATION</p><dl><div><dt>住所</dt><dd>〒<?= h($store['postal_code']) ?><br><?= h($store['prefecture'].$store['city'].$store['address_line'].$store['building']) ?></dd></div><div><dt>営業時間</dt><dd><?= h($store['business_hours'] ?: 'お問い合わせください') ?></dd></div><div><dt>定休日</dt><dd><?= h($store['holidays'] ?: 'お問い合わせください') ?></dd></div><?php if ($store['manager_name'] !== ''): ?><div><dt>責任者</dt><dd><?= h($store['manager_name']) ?></dd></div><?php endif; ?></dl><?php if ($store['phone'] !== ''): ?><a class="store-phone" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $store['phone'])) ?>"><small>電話で相談する</small><strong><?= h($store['phone']) ?></strong></a><?php endif; ?><div class="store-links"><?php if ($store['line_url'] !== ''): ?><a href="<?= h($store['line_url']) ?>" target="_blank" rel="noopener noreferrer">LINEで相談</a><?php endif; ?><?php if ($store['accepts_reservations'] && $reservationUrl !== ''): ?><a class="primary" href="<?= h($reservationUrl) ?>">来店予約を希望する</a><?php endif; ?></div></aside></section>
<section class="store-content"><div class="store-story"><p class="store-eyebrow">ABOUT THIS STORE</p><h2>この店舗について</h2><?php if ($store['description'] !== ''): ?><p><?= nl2br(h($store['description'])) ?></p><?php else: ?><p>店舗紹介を準備しています。厨房機器や厨房づくりについて、お気軽に店舗へお問い合わせください。</p><?php endif; ?><?php if ($store['specialties'] !== ''): ?><h3>得意な業態・ご相談</h3><p><?= nl2br(h($store['specialties'])) ?></p><?php endif; ?></div><aside class="store-service-box"><p class="store-eyebrow">SUPPORT</p><h2>対応サービス</h2><?php if ($services !== []): ?><ul><?php foreach ($services as $service): ?><li><?= h($service) ?></li><?php endforeach; ?></ul><?php else: ?><p>対応内容は店舗へお問い合わせください。</p><?php endif; ?><?php if ($store['service_area'] !== ''): ?><h3>対応地域</h3><p><?= nl2br(h($store['service_area'])) ?></p><?php endif; ?></aside></section>
<?php if ($store['map_url'] !== ''): ?><section class="store-map"><header><p class="store-eyebrow">ACCESS</p><h2>アクセス</h2></header><iframe src="<?= h($store['map_url']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?= h($store['name']) ?>の地図"></iframe></section><?php endif; ?>
<section class="store-bottom-cta"><div><p class="store-eyebrow">CONSULTATION</p><h2>厨房機器から、厨房づくりまで。</h2><p>商品探し、レイアウト、入替、開業準備についてご相談ください。</p></div><?php if ($store['phone'] !== ''): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $store['phone'])) ?>">この店舗へ電話する</a><?php endif; ?></section>
<?php endif; ?></main><footer class="public-footer"><strong>プロ厨房HIT</strong><small>株式会社アイリテクノ</small></footer>
<script>document.querySelectorAll('.store-thumbnails button').forEach(function(button){button.addEventListener('click',function(){var main=document.getElementById('store-main-photo');if(main){main.src=button.dataset.image;document.querySelectorAll('.store-thumbnails button').forEach(function(item){item.classList.remove('active')});button.classList.add('active')}})});</script></body></html>
