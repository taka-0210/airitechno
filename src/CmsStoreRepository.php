<?php
declare(strict_types=1);

final class CmsStoreRepository
{
    private const FIELDS = [
        'kitchen_store_id', 'name', 'slug', 'store_type', 'status', 'postal_code',
        'prefecture', 'city', 'address_line', 'building', 'phone', 'fax', 'email',
        'business_hours', 'holidays', 'service_area', 'catchphrase', 'description',
        'specialties', 'services', 'manager_name', 'manager_staff_id', 'map_url', 'line_url', 'website_url',
        'main_image', 'accepts_reservations', 'reservation_note', 'sort_order',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM cms_stores ORDER BY sort_order, id')->fetchAll();
    }

    public function published(): array
    {
        $statement = $this->pdo->query("SELECT * FROM cms_stores WHERE status = 'published' ORDER BY sort_order, id");
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cms_stores WHERE id = :id');
        $statement->execute(['id' => $id]);
        $store = $statement->fetch();
        return is_array($store) ? $store : null;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM cms_stores WHERE slug = :slug AND status = 'published'");
        $statement->execute(['slug' => $slug]);
        $store = $statement->fetch();
        return is_array($store) ? $store : null;
    }

    public function save(array $input, ?int $id = null): int
    {
        $now = date(DATE_ATOM);
        $data = $this->normalize($input);
        $data['published_at'] = $data['status'] === 'published'
            ? (string) ($input['published_at'] ?? $now)
            : null;
        $data['updated_at'] = $now;

        if ($id === null) {
            $data['created_at'] = $now;
            $columns = array_keys($data);
            $sql = 'INSERT INTO cms_stores (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')';
            $this->pdo->prepare($sql)->execute($data);
            return (int) $this->pdo->lastInsertId();
        }

        $assignments = array_map(static fn(string $field): string => $field . ' = :' . $field, array_keys($data));
        $data['id'] = $id;
        $this->pdo->prepare('UPDATE cms_stores SET ' . implode(', ', $assignments) . ' WHERE id = :id')->execute($data);
        return $id;
    }

    private function normalize(array $input): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = match ($field) {
                'accepts_reservations' => empty($input[$field]) ? 0 : 1,
                'sort_order' => (int) ($input[$field] ?? 0),
                default => trim((string) ($input[$field] ?? '')),
            };
        }

        if ($data['name'] === '' || $data['slug'] === '') {
            throw new InvalidArgumentException('店舗名とURLスラッグは必須です。');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $data['slug'])) {
            throw new InvalidArgumentException('URLスラッグは半角英小文字・数字・ハイフンで入力してください。');
        }
        if (!in_array($data['status'], ['draft', 'published', 'private', 'archived'], true)) {
            $data['status'] = 'draft';
        }
        if (!in_array($data['store_type'], ['direct', 'fc', 'office', 'warehouse'], true)) {
            $data['store_type'] = 'fc';
        }
        return $data;
    }
}
