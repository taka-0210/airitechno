<?php
declare(strict_types=1);

final class CmsAuth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $statement = $this->pdo->prepare('SELECT * FROM cms_users WHERE email = :email AND is_active = 1 LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['cms_user_id'] = (int) $user['id'];
        $this->log((int) $user['id'], 'login', 'user', (int) $user['id']);
        return true;
    }

    public function hasUsers(): bool
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM cms_users')->fetchColumn() > 0;
    }

    public function createInitialAdmin(string $email, string $displayName, string $password): int
    {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('正しいメールアドレスを入力してください。');
        }
        if ($displayName === '') {
            throw new InvalidArgumentException('表示名を入力してください。');
        }
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('パスワードは12文字以上で入力してください。');
        }

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            if ($this->hasUsers()) {
                throw new RuntimeException('初期設定はすでに完了しています。');
            }
            $now = date(DATE_ATOM);
            $statement = $this->pdo->prepare(
                "INSERT INTO cms_users (email, password_hash, display_name, role, is_active, created_at, updated_at)
                 VALUES (:email, :password_hash, :display_name, 'system_admin', 1, :created_at, :updated_at)"
            );
            $statement->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $displayName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->log($userId, 'initial_setup', 'user', $userId);
            $this->pdo->exec('COMMIT');
            return $userId;
        } catch (Throwable $exception) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (PDOException) {
                // The transaction may already be closed by the driver.
            }
            throw $exception;
        }
    }

    public function user(): ?array
    {
        $userId = (int) ($_SESSION['cms_user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, email, display_name, role FROM cms_users WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    public function logout(): void
    {
        $userId = (int) ($_SESSION['cms_user_id'] ?? 0);
        if ($userId > 0) {
            $this->log($userId, 'logout', 'user', $userId);
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function log(?int $userId, string $action, string $entityType, ?int $entityId, string $detail = ''): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cms_audit_logs (user_id, action, entity_type, entity_id, detail, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :detail, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'detail' => $detail,
            'created_at' => date(DATE_ATOM),
        ]);
    }
}
