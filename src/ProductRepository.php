<?php
declare(strict_types=1);

final class ProductRepository
{
    private string $source = 'sample';
    private bool $stale = false;
    private array $pagination = [];

    public function __construct(
        private readonly ?ProChuboApiClient $client,
        private readonly string $storeId,
        private readonly string $cachePath,
        private readonly string $samplePath,
        private readonly int $cacheTtl
    ) {
    }

    public function all(): array
    {
        if ($this->client !== null) {
            try {
                $products = $this->normaliseMany($this->client->products($this->storeId));
                $this->writeCache($products);
                $this->source = 'api';
                return $products;
            } catch (Throwable) {
                $cached = $this->readJson($this->cachePath);
                if ($cached !== null) {
                    $this->source = 'cache';
                    $this->stale = true;
                    return $this->normaliseMany($cached);
                }
            }
        }

        $cached = $this->readJson($this->cachePath);
        if ($cached !== null && filemtime($this->cachePath) >= time() - $this->cacheTtl) {
            $this->source = 'cache';
            return $this->normaliseMany($cached);
        }

        $sample = $this->readJson($this->samplePath) ?? [];
        $this->source = 'sample';
        return $this->normaliseMany($sample);
    }

    public function find(string $id): ?array
    {
        if ($this->client !== null) {
            try {
                $product = $this->normalise($this->client->product($id));
                $this->source = 'api';
                return $product;
            } catch (Throwable) {
                // Use the cached list or local development sample below.
            }
        }
        foreach ($this->all() as $product) {
            if ((string) $product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    public function categories(): array
    {
        if ($this->client !== null) {
            try {
                $categories = $this->client->categories($this->storeId);
                $this->source = 'api';
                return $this->normaliseCategories($categories);
            } catch (Throwable) {
                // Build categories from cached or sample products below.
            }
        }
        return $this->categoriesFromProducts($this->all());
    }

    public function byCategory(string $class1, int $class2Id, int $page = 1, int $perPage = 24): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        if ($this->client !== null) {
            try {
                $result = $this->client->productsPage($this->storeId, [
                    'class1' => $class1,
                    'class2_id' => $class2Id,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);
                $this->source = 'api';
                $this->pagination = $result['meta'];
                return $this->normaliseMany($result['data']);
            } catch (Throwable) {
                // Use cached or sample products below.
            }
        }
        $products = array_values(array_filter($this->all(), static fn (array $product): bool =>
            $product['category']['class1'] === $class1 && $product['category']['class2_id'] === $class2Id
        ));
        $total = count($products);
        $this->pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
        return array_slice($products, ($page - 1) * $perPage, $perPage);
    }

    public function pagination(): array
    {
        return $this->pagination;
    }

    public function meta(): array
    {
        return ['source' => $this->source, 'stale' => $this->stale];
    }

    private function normaliseMany(array $products): array
    {
        return array_values(array_map([$this, 'normalise'], array_filter($products, 'is_array')));
    }

    private function normalise(array $product): array
    {
        $price = is_array($product['price'] ?? null) ? $product['price'] : [];
        $inventory = is_array($product['inventory'] ?? null) ? $product['inventory'] : [];
        $dimensions = is_array($product['dimensions_mm'] ?? null) ? $product['dimensions_mm'] : [];
        $category = is_array($product['category'] ?? null) ? $product['category'] : [];
        $images = array_values(array_filter($product['images'] ?? [], static fn ($image): bool => is_array($image) && !empty($image['url'])));

        return [
            'id' => (string) ($product['id'] ?? $product['jancode'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'manufacturer' => (string) ($product['manufacturer'] ?? $product['maker'] ?? ''),
            'model_number' => (string) ($product['model_number'] ?? $product['model'] ?? ''),
            'year' => (int) ($product['year'] ?? 0),
            'condition' => is_array($product['condition'] ?? null) ? $product['condition'] : ['code' => '', 'label' => ''],
            'dimensions_mm' => [
                'width' => (int) ($dimensions['width'] ?? $product['width'] ?? 0),
                'depth' => (int) ($dimensions['depth'] ?? $product['depth'] ?? 0),
                'height' => (int) ($dimensions['height'] ?? $product['height'] ?? 0),
            ],
            'price' => [
                'tax_excluded' => (int) ($price['tax_excluded'] ?? $product['price_tax_excluded'] ?? 0),
                'tax_included' => (int) ($price['tax_included'] ?? $product['price_tax_included'] ?? 0),
                'currency' => (string) ($price['currency'] ?? 'JPY'),
                'ask' => (bool) ($price['ask'] ?? false),
            ],
            'inventory' => [
                'status' => (string) ($inventory['status'] ?? $product['status'] ?? ''),
                'store_id' => (string) ($inventory['store_id'] ?? $this->storeId),
                'store_name' => (string) ($inventory['store_name'] ?? 'プロ厨房ヒット新居浜店'),
            ],
            'category' => [
                'class1' => (string) ($category['class1'] ?? $product['class1'] ?? ''),
                'class2_id' => (int) ($category['class2_id'] ?? $product['class2_id'] ?? 0),
                'class2' => (string) ($category['class2'] ?? $product['class2'] ?? ''),
            ],
            'images' => $images,
            'videos' => array_values(array_filter($product['videos'] ?? [], 'is_array')),
            'description' => (string) ($product['description'] ?? ''),
            'updated_at' => (string) ($product['updated_at'] ?? ''),
        ];
    }

    private function normaliseCategories(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $class1 = (string) ($row['class1'] ?? $row['name'] ?? '');
            if ($class1 === '') {
                continue;
            }
            $class2Rows = $row['children'] ?? $row['class2'] ?? $row['categories'] ?? null;
            if (is_array($class2Rows) && array_is_list($class2Rows)) {
                foreach ($class2Rows as $child) {
                    if (is_array($child)) {
                        $this->addCategoryRow($grouped, $class1, $child);
                    }
                }
            } else {
                $this->addCategoryRow($grouped, $class1, $row);
            }
        }
        return array_values($grouped);
    }

    private function addCategoryRow(array &$grouped, string $class1, array $row): void
    {
        $id = (int) ($row['class2_id'] ?? $row['id'] ?? 0);
        $name = (string) ($row['class2'] ?? $row['name'] ?? '');
        $count = (int) ($row['count'] ?? $row['product_count'] ?? 0);
        if (!isset($grouped[$class1])) {
            $grouped[$class1] = ['name' => $class1, 'count' => 0, 'children' => []];
        }
        if ($id > 0 && $name !== '') {
            $grouped[$class1]['children'][] = ['id' => $id, 'name' => $name, 'count' => $count];
        }
        $grouped[$class1]['count'] += $count;
    }

    private function categoriesFromProducts(array $products): array
    {
        $grouped = [];
        foreach ($products as $product) {
            $category = $product['category'];
            $class1 = $category['class1'];
            if ($class1 === '' || $category['class2_id'] < 1 || $category['class2'] === '') {
                continue;
            }
            if (!isset($grouped[$class1])) {
                $grouped[$class1] = ['name' => $class1, 'count' => 0, 'children' => []];
            }
            $id = $category['class2_id'];
            if (!isset($grouped[$class1]['children'][$id])) {
                $grouped[$class1]['children'][$id] = ['id' => $id, 'name' => $category['class2'], 'count' => 0];
            }
            $grouped[$class1]['children'][$id]['count']++;
            $grouped[$class1]['count']++;
        }
        foreach ($grouped as &$category) {
            $category['children'] = array_values($category['children']);
        }
        unset($category);
        return array_values($grouped);
    }

    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(array $products): void
    {
        $directory = dirname($this->cachePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $temporary = $this->cachePath . '.tmp';
        file_put_contents($temporary, json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        rename($temporary, $this->cachePath);
    }
}
