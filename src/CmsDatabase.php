<?php
declare(strict_types=1);

final class CmsDatabase
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('CMSデータ保存先を作成できません。');
        }

        $this->pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cms_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'system_admin',
    store_id INTEGER,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cms_stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kitchen_store_id TEXT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    store_type TEXT NOT NULL DEFAULT 'fc',
    status TEXT NOT NULL DEFAULT 'draft',
    postal_code TEXT NOT NULL DEFAULT '',
    prefecture TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    address_line TEXT NOT NULL DEFAULT '',
    building TEXT NOT NULL DEFAULT '',
    phone TEXT NOT NULL DEFAULT '',
    fax TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    business_hours TEXT NOT NULL DEFAULT '',
    holidays TEXT NOT NULL DEFAULT '',
    service_area TEXT NOT NULL DEFAULT '',
    catchphrase TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    specialties TEXT NOT NULL DEFAULT '',
    services TEXT NOT NULL DEFAULT '',
    manager_name TEXT NOT NULL DEFAULT '',
    manager_staff_id TEXT,
    map_url TEXT NOT NULL DEFAULT '',
    line_url TEXT NOT NULL DEFAULT '',
    website_url TEXT NOT NULL DEFAULT '',
    main_image TEXT NOT NULL DEFAULT '',
    accepts_reservations INTEGER NOT NULL DEFAULT 0,
    reservation_note TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    published_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cms_audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    detail TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES cms_users(id)
);

CREATE TABLE IF NOT EXISTS cms_staff_directory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kitchen_staff_id TEXT NOT NULL UNIQUE,
    store_id INTEGER,
    display_name TEXT NOT NULL,
    job_title TEXT NOT NULL DEFAULT '',
    profile TEXT NOT NULL DEFAULT '',
    photo_path TEXT NOT NULL DEFAULT '',
    web_publishable INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    synced_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (store_id) REFERENCES cms_stores(id)
);

CREATE TABLE IF NOT EXISTS cms_store_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL,
    file_path TEXT NOT NULL,
    alt_text TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    source_url TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (store_id) REFERENCES cms_stores(id) ON DELETE CASCADE
);
SQL);

        $this->addColumnIfMissing('cms_users', 'store_id', 'INTEGER');
        $this->addColumnIfMissing('cms_stores', 'manager_staff_id', 'TEXT');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_users_store_id ON cms_users(store_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_staff_store_id ON cms_staff_directory(store_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_cms_store_images_store_id ON cms_store_images(store_id)');
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_cms_store_images_source_url ON cms_store_images(source_url) WHERE source_url IS NOT NULL');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $columns = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $existing) {
            if ((string) $existing['name'] === $column) {
                return;
            }
        }
        $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}
