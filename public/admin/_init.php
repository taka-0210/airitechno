<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('airitechno_cms');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

$cmsAuth = new CmsAuth(cms_database()->pdo());

function admin_url(string $path = ''): string
{
    return public_url('admin/' . ltrim($path, '/'));
}

function require_admin(): array
{
    global $cmsAuth;
    $user = $cmsAuth->user();
    if ($user === null) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('セッションの有効期限が切れました。前の画面へ戻って再度お試しください。');
    }
}

function admin_flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function take_admin_flash(): string
{
    $message = (string) ($_SESSION['flash'] ?? '');
    unset($_SESSION['flash']);
    return $message;
}

function store_image_upload(int $storeId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('画像をアップロードできませんでした。8MB以下の画像を選択してください。');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('JPEG、PNG、WebP形式の画像を選択してください。');
    }

    $relativeDirectory = 'uploads/stores/' . $storeId;
    $directory = dirname(__DIR__) . '/' . $relativeDirectory;
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('画像保存先を作成できません。');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('画像を保存できませんでした。');
    }
    return $relativeDirectory . '/' . $filename;
}

function store_gallery_uploads(int $storeId, array $files): array
{
    $saved = [];
    $names = $files['name'] ?? [];
    if (!is_array($names)) {
        return $saved;
    }
    foreach (array_keys($names) as $index) {
        $file = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
        $path = store_image_upload($storeId, $file);
        if ($path !== null) {
            $saved[] = $path;
        }
    }
    return $saved;
}

function quarantine_store_image(int $storeId, string $relativePath): void
{
    $expectedDirectory = realpath(dirname(__DIR__) . '/uploads/stores/' . $storeId);
    $sourcePath = realpath(dirname(__DIR__) . '/' . ltrim($relativePath, '/'));
    if ($expectedDirectory === false || $sourcePath === false || !str_starts_with($sourcePath, $expectedDirectory . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('画像ファイルの保存場所を確認できません。');
    }

    $quarantineDirectory = AIRITECHNO_ROOT . '/storage/quarantine/store-images/' . $storeId;
    if (!is_dir($quarantineDirectory) && !mkdir($quarantineDirectory, 0770, true) && !is_dir($quarantineDirectory)) {
        throw new RuntimeException('画像の隔離先を作成できません。');
    }
    $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
    $targetPath = $quarantineDirectory . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . ($extension !== '' ? '.' . $extension : '');
    if (!rename($sourcePath, $targetPath)) {
        throw new RuntimeException('画像を公開領域から移動できませんでした。');
    }
}

function render_admin_header(string $title, ?array $user = null): void
{
    ?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> | プロ厨房HIT CMS</title><link rel="stylesheet" href="<?= h(admin_url('assets/admin.css')) ?>"></head>
<body><header class="admin-header"><a href="<?= h(admin_url()) ?>"><strong>プロ厨房HIT CMS</strong></a>
<?php if ($user !== null): ?><nav><span><?= h($user['display_name']) ?></span><a href="<?= h(admin_url('logout.php')) ?>">ログアウト</a></nav><?php endif; ?></header>
<?php if ($user !== null): ?><div class="admin-shell"><aside><a href="<?= h(admin_url()) ?>">ダッシュボード</a><a href="<?= h(admin_url('stores/')) ?>">店舗</a><span>導入事例（準備中）</span><span>記事・お知らせ（準備中）</span><span>バナー（準備中）</span></aside><main><?php else: ?><main class="login-main"><?php endif;
}

function render_admin_footer(?array $user = null): void
{
    ?></main><?php if ($user !== null): ?></div><?php endif; ?></body></html><?php
}
