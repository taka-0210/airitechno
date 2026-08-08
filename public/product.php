<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$id = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['id'] ?? ''));
$repository = product_repository();
$product = $id !== '' ? $repository->find($id) : null;
if ($product === null) http_response_code(404);
$category = $product['category'] ?? ['class1' => '', 'class2_id' => 0, 'class2' => ''];
$dimensions = $product['dimensions_mm'] ?? [];
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($product['name'] ?? '商品なし')?>（テスト版）</title></head><body>
<header><p><a href="index.php">商品情報</a><?php if ($product !== null && $category['class1'] !== ''): ?> &gt; <a href="category.php?class1=<?=rawurlencode($category['class1'])?>"><?=h($category['class1'])?></a> &gt; <a href="products.php?class1=<?=rawurlencode($category['class1'])?>&amp;class2_id=<?=$category['class2_id']?>"><?=h($category['class2'])?></a><?php endif; ?><?php if ($product !== null): ?> &gt; <?=h($product['name'])?><?php endif; ?></p></header><hr><main>
<?php if ($product === null): ?><h1>商品が見つかりません</h1><p>販売済みなどの理由で掲載を終了した可能性があります。</p>
<?php else: ?><h1><?=h($product['name'])?></h1><p><?=h(product_status_label($product['inventory']['status']))?></p>
<?php if ($product['images'] !== []): ?><p><img src="<?=h($product['images'][0]['url'])?>" alt="<?=h($product['name'])?>" width="400"></p><?php endif; ?>
<h2>商品情報</h2><dl>
<dt>販売価格</dt><dd><?php if ($product['price']['ask']): ?>お問い合わせください<?php else: ?>税込 <?=number_format($product['price']['tax_included'])?>円<?php endif; ?></dd>
<dt>商品番号</dt><dd><?=h($product['id'])?></dd><dt>メーカー</dt><dd><?=h($product['manufacturer'] ?: '―')?></dd><dt>型式</dt><dd><?=h($product['model_number'] ?: '―')?></dd><dt>年式</dt><dd><?=$product['year'] > 0 ? h($product['year'].'年') : '―'?></dd><dt>商品状態</dt><dd><?=h($product['condition']['label'] ?? '―')?></dd>
<dt>外形寸法</dt><dd>W<?=number_format((int)($dimensions['width']??0))?> × D<?=number_format((int)($dimensions['depth']??0))?> × H<?=number_format((int)($dimensions['height']??0))?> mm</dd><dt>在庫店舗</dt><dd><?=h($product['inventory']['store_name'])?></dd></dl>
<?php if ($product['description'] !== ''): ?><h2>商品説明</h2><p><?=nl2br(h($product['description']))?></p><?php endif; ?><p><a href="<?=h(product_contact_url($product))?>">この商品について問い合わせる</a></p>
<?php endif; ?></main><hr><footer><small>株式会社アイリテクノ</small></footer></body></html>
