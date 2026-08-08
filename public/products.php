<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$class1 = trim((string) ($_GET['class1'] ?? ''));
$class2Id = max(0, (int) ($_GET['class2_id'] ?? 0));
$page = max(1, (int) ($_GET['page'] ?? 1));
$repository = product_repository();
$selected = null;
foreach ($repository->categories() as $category) {
    if ($category['name'] !== $class1) continue;
    foreach ($category['children'] as $child) if ($child['id'] === $class2Id) $selected = $child;
}
if ($selected === null) { http_response_code(404); $products = []; $pagination = []; }
else { $products = $repository->byCategory($class1, $class2Id, $page); $pagination = $repository->pagination(); }
$totalPages = max(1, (int) ($pagination['total_pages'] ?? $pagination['last_page'] ?? 1));
?>
<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($selected['name'] ?? '商品なし')?>（テスト版）</title></head><body>
<header><p><a href="index.php">商品情報</a> &gt; <a href="category.php?class1=<?=rawurlencode($class1)?>"><?=h($class1)?></a> &gt; <?=h($selected['name'] ?? '分類なし')?></p></header><hr><main>
<?php if ($selected === null): ?><h1>商品カテゴリーが見つかりません</h1>
<?php else: ?><h1><?=h($selected['name'])?></h1><p><?=number_format((int) $selected['count'])?>点</p>
<?php if ($products === []): ?><p>現在掲載中の商品はありません。</p><?php else: ?><ul>
<?php foreach ($products as $product): ?><li><p><strong><a href="product.php?id=<?=rawurlencode($product['id'])?>"><?=h($product['name'])?></a></strong><br><?=h($product['manufacturer'])?> / <?=h($product['model_number'])?><br><?php if ($product['price']['ask']): ?>価格はお問い合わせください<?php else: ?>税込 <?=number_format($product['price']['tax_included'])?>円<?php endif; ?> / <?=h(product_status_label($product['inventory']['status']))?></p></li><?php endforeach; ?>
</ul><?php endif; ?>
<?php if ($totalPages > 1): ?><p>ページ：<?php for ($number=1;$number<=$totalPages;$number++): ?> <a href="?class1=<?=rawurlencode($class1)?>&amp;class2_id=<?=$class2Id?>&amp;page=<?=$number?>"><?=$number?></a><?php endfor; ?></p><?php endif; ?>
<?php endif; ?></main><hr><footer><small>株式会社アイリテクノ</small></footer></body></html>
