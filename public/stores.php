<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$stores = cms_store_repository()->published();
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>店舗一覧 | プロ厨房HIT</title><link rel="stylesheet" href="assets/catalogue.css"></head><body>
<header class="site-header"><a class="brand" href="index.php"><small>厨房づくりと厨房機器</small><strong>プロ厨房HIT</strong></a></header><main><section class="hero"><p class="eyebrow">STORES</p><h1>店舗を探す</h1><p>厨房機器と厨房づくりを相談できる、お近くのプロ厨房HITをご案内します。</p></section><section class="catalogue"><div class="product-grid">
<?php foreach ($stores as $store): ?><article class="product-card"><?php if ($store['main_image'] !== ''): ?><a class="product-image" href="store.php?slug=<?= rawurlencode($store['slug']) ?>"><img src="<?= h(public_url($store['main_image'])) ?>" alt="<?= h($store['name']) ?>"></a><?php endif; ?><div class="product-copy"><p class="maker"><?= h($store['prefecture'] . $store['city']) ?></p><h2><a href="store.php?slug=<?= rawurlencode($store['slug']) ?>"><?= h($store['name']) ?></a></h2><p><?= h($store['catchphrase']) ?></p><a class="detail-link" href="store.php?slug=<?= rawurlencode($store['slug']) ?>">店舗詳細を見る</a></div></article><?php endforeach; ?>
<?php if ($stores === []): ?><p>店舗情報を準備中です。</p><?php endif; ?></div></section></main><footer>株式会社アイリテクノ</footer></body></html>
