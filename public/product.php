<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$id = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['id'] ?? ''));
$repository = product_repository();
$product = $id !== '' ? $repository->find($id) : null;
if ($product === null) {
    http_response_code(404);
}
$dimensions = $product['dimensions_mm'] ?? [];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($product['name'] ?? '商品が見つかりません')?>｜プロ厨房ヒット新居浜店</title>
  <link rel="stylesheet" href="assets/catalogue.css">
</head>
<body>
<header class="site-header"><a class="brand" href="index.php"><small>株式会社アイリテクノ</small><strong>プロ厨房ヒット新居浜店</strong></a></header>
<main class="detail-main">
<?php if ($product === null): ?>
  <section class="not-found"><h1>商品が見つかりません</h1><p>販売済みなどの理由で掲載を終了した可能性があります。</p><a class="button" href="index.php">商品一覧へ戻る</a></section>
<?php else: ?>
  <nav class="breadcrumb"><a href="index.php">商品一覧</a><span>›</span><span><?=h($product['name'])?></span></nav>
  <article class="product-detail">
    <section class="gallery">
      <?php foreach ($product['images'] as $index => $image): ?>
        <img src="<?=h($image['url'])?>" alt="<?=h($product['name'])?><?=count($product['images']) > 1 ? ' ' . ($index + 1) : ''?>" <?= $index > 0 ? 'loading="lazy"' : '' ?>>
      <?php endforeach; ?>
      <?php if ($product['images'] === []): ?><div class="no-image">NO IMAGE</div><?php endif; ?>
    </section>
    <section class="detail-copy">
      <span class="status status-<?=h($product['inventory']['status'])?>"><?=h(product_status_label($product['inventory']['status']))?></span>
      <p class="maker"><?=h($product['manufacturer'])?></p>
      <h1><?=h($product['name'])?></h1>
      <p class="model">型式 <?=h($product['model_number'] ?: '―')?></p>
      <p class="detail-price"><?php if ($product['price']['ask']): ?>価格はお問い合わせください<?php else: ?><strong>¥<?=number_format($product['price']['tax_included'])?></strong><span>税込</span><small>税別 ¥<?=number_format($product['price']['tax_excluded'])?></small><?php endif; ?></p>
      <dl class="specs">
        <div><dt>商品番号</dt><dd><?=h($product['id'])?></dd></div>
        <div><dt>メーカー</dt><dd><?=h($product['manufacturer'] ?: '―')?></dd></div>
        <div><dt>年式</dt><dd><?=$product['year'] > 0 ? h($product['year'] . '年') : '―'?></dd></div>
        <div><dt>商品状態</dt><dd><?=h($product['condition']['label'] ?? '―')?></dd></div>
        <div><dt>外形寸法</dt><dd>W<?=number_format((int) ($dimensions['width'] ?? 0))?> × D<?=number_format((int) ($dimensions['depth'] ?? 0))?> × H<?=number_format((int) ($dimensions['height'] ?? 0))?> mm</dd></div>
        <div><dt>在庫店舗</dt><dd><?=h($product['inventory']['store_name'])?></dd></div>
      </dl>
      <?php if ($product['description'] !== ''): ?><div class="description"><h2>商品説明</h2><p><?=nl2br(h($product['description']))?></p></div><?php endif; ?>
      <a class="button inquiry" href="<?=h(product_contact_url($product))?>">この商品について問い合わせる</a>
      <p class="inventory-note">店頭でも販売しているため、掲載中でも売約済みの場合があります。</p>
    </section>
  </article>
<?php endif; ?>
</main>
<footer><p>© 株式会社アイリテクノ / プロ厨房ヒット新居浜店</p></footer>
</body>
</html>
