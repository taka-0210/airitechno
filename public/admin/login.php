<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';

if ($cmsAuth->user() !== null) {
    header('Location: ' . admin_url());
    exit;
}

if (!$cmsAuth->hasUsers()) {
    header('Location: ' . admin_url('setup.php'));
    exit;
}

$error = '';
$notice = isset($_GET['setup']) ? '初期設定が完了しました。設定したメールアドレスとパスワードでログインしてください。' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($cmsAuth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: ' . admin_url());
        exit;
    }
    usleep(300000);
    $error = 'メールアドレスまたはパスワードが正しくありません。';
}

render_admin_header('ログイン');
?><section class="login-card"><p class="eyebrow">ADMINISTRATION</p><h1>管理画面ログイン</h1>
<?php if ($notice !== ''): ?><p class="alert success"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="alert error"><?= h($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
<label>メールアドレス<input type="email" name="email" required autocomplete="username"></label>
<label>パスワード<input type="password" name="password" required autocomplete="current-password"></label>
<button type="submit">ログイン</button></form></section><?php render_admin_footer();
