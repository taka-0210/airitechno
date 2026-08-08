<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$class1 = trim((string) ($_GET['class1'] ?? ''));
$repository = product_repository();
$category = null;
foreach ($repository->categories() as $candidate) {
    if ($candidate['name'] === $class1) { $category = $candidate; break; }
}
if ($category === null) http_response_code(404);
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($category['name'] ?? '分類なし')?>（テスト版）</title></head><body>
<header><p><a href="index.php">商品情報</a> &gt; <?=h($category['name'] ?? '分類なし')?></p></header><hr><main>
<?php if ($category === null): ?><h1>カテゴリーが見つかりません</h1><p><a href="index.php">大分類へ戻る</a></p>
<?php else: ?><h1><?=h($category['name'])?></h1><p>中分類を選んでください。</p><h2>中分類</h2><ul>
<?php foreach ($category['children'] as $child): ?><li><a href="products.php?class1=<?=rawurlencode($category['name'])?>&amp;class2_id=<?=$child['id']?>"><?=h($child['name'])?></a>（<?=number_format($child['count'])?>点）</li><?php endforeach; ?>
</ul><?php endif; ?></main><hr><footer><small>株式会社アイリテクノ</small></footer></body></html>
