<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_init.php';
$user = require_admin();
$stores = cms_store_repository()->all();
$flash = take_admin_flash();
$statusLabels = ['draft' => '下書き', 'published' => '公開', 'private' => '非公開', 'archived' => 'アーカイブ'];
render_admin_header('店舗', $user);
?><div class="page-head"><div><p class="eyebrow">STORES</p><h1>店舗</h1></div><a class="button" href="edit.php">店舗を追加</a></div>
<?php if ($flash !== ''): ?><p class="alert success"><?= h($flash) ?></p><?php endif; ?>
<section class="panel table-wrap"><table><thead><tr><th>店舗名</th><th>区分</th><th>厨房君ID</th><th>状態</th><th>更新日</th><th></th></tr></thead><tbody>
<?php if ($stores === []): ?><tr><td colspan="6">店舗はまだ登録されていません。</td></tr><?php endif; ?>
<?php foreach ($stores as $store): ?><tr><td><strong><?= h($store['name']) ?></strong><small>/<?= h($store['slug']) ?></small></td><td><?= h($store['store_type']) ?></td><td><?= h($store['kitchen_store_id']) ?></td><td><span class="badge <?= h($store['status']) ?>"><?= h($statusLabels[$store['status']] ?? $store['status']) ?></span></td><td><?= h(date('Y-m-d H:i', strtotime($store['updated_at']))) ?></td><td><a href="edit.php?id=<?= (int) $store['id'] ?>">編集</a></td></tr><?php endforeach; ?>
</tbody></table></section><?php render_admin_footer($user);
