<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$repository = product_repository();
$categories = $repository->categories();
$meta = $repository->meta();
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>商品情報（テスト版）</title></head><body>
<header><p><a href="index.php">プロ厨房ヒット新居浜店 商品情報</a></p></header><hr>
<main>
<h1>商品情報（テスト版）</h1>
<p>大分類を選んでください。</p>
<?php if ($meta['source'] === 'sample'): ?><p>※ 確認用データを表示中</p><?php elseif ($meta['stale']): ?><p>※ 保存済みの在庫情報を表示中</p><?php endif; ?>
<h2>大分類</h2>
<?php if ($categories === []): ?><p>掲載中の商品はありません。</p><?php else: ?><ul>
<?php foreach ($categories as $category): ?><li><a href="category.php?class1=<?=rawurlencode($category['name'])?>"><?=h($category['name'])?></a>（<?=number_format($category['count'])?>点）</li><?php endforeach; ?>
</ul><?php endif; ?>
</main><hr><footer><small>株式会社アイリテクノ</small></footer></body></html>
