<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$email = strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
    fwrite(STDERR, "Usage: php scripts/create-cms-admin.php email@example.com \"表示名\"\n");
    exit(1);
}

fwrite(STDOUT, 'Password: ');
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$now = date(DATE_ATOM);
$statement = cms_database()->pdo()->prepare(<<<'SQL'
INSERT INTO cms_users (email, password_hash, display_name, role, is_active, created_at, updated_at)
VALUES (:email, :password_hash, :display_name, 'system_admin', 1, :created_at, :updated_at)
ON CONFLICT(email) DO UPDATE SET
    password_hash = excluded.password_hash,
    display_name = excluded.display_name,
    is_active = 1,
    updated_at = excluded.updated_at
SQL);
$statement->execute([
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'display_name' => $name,
    'created_at' => $now,
    'updated_at' => $now,
]);
fwrite(STDOUT, "CMS administrator saved.\n");
