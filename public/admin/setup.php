<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';

if ($cmsAuth->hasUsers()) {
    header('Location: ' . admin_url('login.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    try {
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new InvalidArgumentException('確認用パスワードが一致しません。');
        }
        $cmsAuth->createInitialAdmin(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['display_name'] ?? ''),
            $password
        );
        header('Location: ' . admin_url('login.php?setup=complete'));
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

render_admin_header('初期設定');
?><section class="login-card setup-card"><p class="eyebrow">FIRST SETUP</p><h1>管理者の初期設定</h1>
<p class="form-help">最初に管理画面を利用する管理者を作成します。この画面は設定完了後、自動的に使用できなくなります。</p>
<?php if ($error !== ''): ?><p class="alert error"><?= h($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="_token" value="<?= h(csrf_token()) ?>">
<label>表示名<input name="display_name" required maxlength="100" value="<?= h($_POST['display_name'] ?? '') ?>" autocomplete="name" placeholder="例：システム管理者"></label>
<label>メールアドレス<input type="email" name="email" required maxlength="254" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="username" placeholder="info@example.com"></label>
<label>パスワード<input type="password" name="password" required minlength="12" autocomplete="new-password"><small>12文字以上で設定してください。</small></label>
<label>パスワード（確認）<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
<button type="submit">管理者を作成する</button></form></section><?php render_admin_footer();
