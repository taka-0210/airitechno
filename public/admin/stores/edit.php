<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_init.php';
$user = require_admin();
$repository = cms_store_repository();
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$store = $id > 0 ? $repository->find($id) : null;
if ($id > 0 && $store === null) {
    http_response_code(404);
    exit('店舗が見つかりません。');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $input = $_POST;
        if ($store !== null) {
            $input['main_image'] = $store['main_image'];
            $input['published_at'] = $store['published_at'];
        }
        $savedId = $repository->save($input, $id > 0 ? $id : null);
        $uploaded = store_image_upload($savedId, $_FILES['main_image_file'] ?? []);
        if ($uploaded !== null) {
            $input['main_image'] = $uploaded;
            $repository->save($input, $savedId);
        }
        $gallery = $repository->images($savedId);
        $nextOrder = count($gallery) + 1;
        foreach (store_gallery_uploads($savedId, $_FILES['gallery_files'] ?? []) as $galleryPath) {
            $repository->addImage($savedId, $galleryPath, (string) ($input['name'] ?? ''), $nextOrder++);
        }
        $cmsAuth->log((int) $user['id'], $id > 0 ? 'update' : 'create', 'store', $savedId, (string) ($input['name'] ?? ''));
        admin_flash('店舗情報を保存しました。');
        header('Location: index.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof PDOException && str_contains($exception->getMessage(), 'UNIQUE')
            ? '同じURLスラッグがすでに使われています。'
            : $exception->getMessage();
        $store = array_replace($store ?? [], $_POST);
    }
}

$store = array_replace([
    'id' => 0, 'kitchen_store_id' => '', 'name' => '', 'slug' => '', 'store_type' => 'fc', 'status' => 'draft',
    'postal_code' => '', 'prefecture' => '', 'city' => '', 'address_line' => '', 'building' => '', 'phone' => '',
    'fax' => '', 'email' => '', 'business_hours' => '', 'holidays' => '', 'service_area' => '', 'catchphrase' => '',
    'description' => '', 'specialties' => '', 'services' => '', 'manager_name' => '', 'manager_staff_id' => '', 'map_url' => '', 'line_url' => '',
    'website_url' => '', 'main_image' => '', 'accepts_reservations' => 0, 'reservation_note' => '', 'sort_order' => 0,
], $store ?? []);
$storeImages = $id > 0 ? $repository->images($id) : [];

render_admin_header($id > 0 ? '店舗を編集' : '店舗を追加', $user);
?><div class="page-head"><div><p class="eyebrow">STORE EDITOR</p><h1><?= $id > 0 ? '店舗を編集' : '店舗を追加' ?></h1></div><a href="index.php">一覧へ戻る</a></div>
<?php if ($error !== ''): ?><p class="alert error"><?= h($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="editor"><input type="hidden" name="_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $store['id'] ?>">
<section class="panel"><h2>基本情報</h2><div class="fields two">
<label>店舗名 <b>必須</b><input name="name" required value="<?= h($store['name']) ?>"></label><label>URLスラッグ <b>必須</b><input name="slug" required pattern="[a-z0-9][a-z0-9-]*" value="<?= h($store['slug']) ?>" placeholder="niihama"></label>
<label>店舗区分<select name="store_type"><?php foreach (['direct'=>'直営','fc'=>'FC','office'=>'営業所','warehouse'=>'倉庫'] as $value=>$label): ?><option value="<?= $value ?>" <?= $store['store_type']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>厨房君店舗ID<input name="kitchen_store_id" value="<?= h($store['kitchen_store_id']) ?>"></label>
<label>公開状態<select name="status"><?php foreach (['draft'=>'下書き','published'=>'公開','private'=>'非公開','archived'=>'アーカイブ'] as $value=>$label): ?><option value="<?= $value ?>" <?= $store['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>表示順<input type="number" name="sort_order" value="<?= (int) $store['sort_order'] ?>"></label>
</div></section>
<section class="panel"><h2>所在地・連絡先</h2><div class="fields two">
<label>郵便番号<input name="postal_code" value="<?= h($store['postal_code']) ?>"></label><label>都道府県<input name="prefecture" value="<?= h($store['prefecture']) ?>"></label><label>市区町村<input name="city" value="<?= h($store['city']) ?>"></label><label>町域・番地<input name="address_line" value="<?= h($store['address_line']) ?>"></label><label>建物名<input name="building" value="<?= h($store['building']) ?>"></label><label>電話番号<input name="phone" value="<?= h($store['phone']) ?>"></label><label>FAX<input name="fax" value="<?= h($store['fax']) ?>"></label><label>メール<input type="email" name="email" value="<?= h($store['email']) ?>"></label><label>営業時間<input name="business_hours" value="<?= h($store['business_hours']) ?>"></label><label>定休日<input name="holidays" value="<?= h($store['holidays']) ?>"></label>
</div><label>対応地域<textarea name="service_area"><?= h($store['service_area']) ?></textarea></label></section>
<section class="panel"><h2>店舗紹介</h2><div class="fields"><label>キャッチコピー<input name="catchphrase" value="<?= h($store['catchphrase']) ?>"></label><label>店舗紹介<textarea name="description" rows="7"><?= h($store['description']) ?></textarea></label><label>得意な業態・相談<textarea name="specialties"><?= h($store['specialties']) ?></textarea></label><label>対応サービス<textarea name="services"><?= h($store['services']) ?></textarea></label><label>店長・責任者<input name="manager_name" value="<?= h($store['manager_name']) ?>"></label><label>メイン画像<input type="file" name="main_image_file" accept="image/jpeg,image/png,image/webp"></label><?php if ($store['main_image'] !== ''): ?><img class="preview-image" src="<?= h(public_url($store['main_image'])) ?>" alt=""><?php endif; ?><label>店舗ギャラリー<input type="file" name="gallery_files[]" accept="image/jpeg,image/png,image/webp" multiple><small>複数の画像をまとめて選択できます。</small></label><?php if ($storeImages !== []): ?><div class="image-grid"><?php foreach ($storeImages as $image): ?><img src="<?= h(public_url($image['file_path'])) ?>" alt="<?= h($image['alt_text']) ?>"><?php endforeach; ?></div><?php endif; ?></div></section>
<section class="panel"><h2>リンク・予約</h2><div class="fields two"><label>GoogleマップURL<input type="url" name="map_url" value="<?= h($store['map_url']) ?>"></label><label>LINE URL<input type="url" name="line_url" value="<?= h($store['line_url']) ?>"></label><label>店舗Webサイト<input type="url" name="website_url" value="<?= h($store['website_url']) ?>"></label><label class="check"><input type="checkbox" name="accepts_reservations" value="1" <?= $store['accepts_reservations'] ? 'checked' : '' ?>>来店予約を受け付ける</label></div><label>予約時の注意<textarea name="reservation_note"><?= h($store['reservation_note']) ?></textarea></label></section>
<div class="actions"><button type="submit">保存する</button><?php if ($id > 0 && $store['status']==='published'): ?><a target="_blank" href="../../store.php?slug=<?= rawurlencode($store['slug']) ?>">公開ページを見る</a><?php endif; ?></div></form><?php render_admin_footer($user);
