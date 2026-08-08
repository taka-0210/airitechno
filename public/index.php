<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$repository = product_repository();
$products = $repository->all();
$meta = $repository->meta();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>商品情報｜プロ厨房ヒット新居浜店</title>
  <meta name="description" content="プロ厨房ヒット新居浜店の中古厨房機器・設備をご紹介します。">
  <link rel="stylesheet" href="assets/catalogue.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="index.php"><small>株式会社アイリテクノ</small><strong>プロ厨房ヒット新居浜店</strong></a>
</header>
<main>
  <section class="hero">
    <p class="eyebrow">USED KITCHEN EQUIPMENT</p>
    <h1>新居浜店の商品情報</h1>
    <p>掲載商品は店頭でも販売しています。最新の在庫状況はお問い合わせください。</p>
  </section>

  <?php if ($meta['source'] === 'sample'): ?>
    <p class="notice">現在はAPI接続前の確認用データを表示しています。</p>
  <?php elseif ($meta['stale']): ?>
    <p class="notice">最新情報を取得できなかったため、直前の在庫情報を表示しています。</p>
  <?php endif; ?>

  <section class="catalogue" aria-labelledby="catalogue-title">
    <div class="section-heading"><h2 id="catalogue-title">取扱商品</h2><span><?=count($products)?>件</span></div>
    <?php if ($products === []): ?>
      <p class="empty">現在掲載中の商品はありません。</p>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product): $image = $product['images'][0]['url'] ?? ''; ?>
          <article class="product-card">
            <a class="product-image" href="product.php?id=<?=rawurlencode($product['id'])?>">
              <?php if ($image !== ''): ?><img src="<?=h($image)?>" alt="<?=h($product['name'])?>" loading="lazy"><?php else: ?><span>NO IMAGE</span><?php endif; ?>
              <span class="status status-<?=h($product['inventory']['status'])?>"><?=h(product_status_label($product['inventory']['status']))?></span>
            </a>
            <div class="product-copy">
              <p class="maker"><?=h($product['manufacturer'])?></p>
              <h3><a href="product.php?id=<?=rawurlencode($product['id'])?>"><?=h($product['name'])?></a></h3>
              <p class="model"><?=h($product['model_number'])?></p>
              <p class="price"><?php if ($product['price']['ask']): ?>価格はお問い合わせください<?php else: ?><strong>¥<?=number_format($product['price']['tax_included'])?></strong><small>（税込）</small><?php endif; ?></p>
              <a class="detail-link" href="product.php?id=<?=rawurlencode($product['id'])?>">商品詳細を見る</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<footer><p>© 株式会社アイリテクノ / プロ厨房ヒット新居浜店</p></footer>
</body>
</html>
