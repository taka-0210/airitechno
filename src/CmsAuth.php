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
