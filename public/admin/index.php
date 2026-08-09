<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
$user = require_admin();
$stores = cms_store_repository()->all();
$published = count(array_filter($stores, static fn(array $store): bool => $store['status'] === 'published'));
render_admin_header('ダッシュボード', $user);
?><div class="page-head"><div><p class="eyebrow">DASHBOARD</p><h1>ダッシュボード</h1></div></div>
<div class="stats"><article><span>店舗</span><strong><?= count($stores) ?></strong></article><article><span>公開中</span><strong><?= $published ?></strong></article></div>
<section class="panel"><h2>CMS構築状況</h2><p>店舗マスターから構築を開始しています。商品情報は厨房君APIを参照し、このCMSには重複保存しません。</p><p><a class="button" href="<?= h(admin_url('stores/')) ?>">店舗を管理する</a></p></section><?php render_admin_footer($user);
