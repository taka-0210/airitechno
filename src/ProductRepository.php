<?php
declare(strict_types=1);

final class ProductRepository
{
    private string $source = 'sample';
    private bool $stale = false;

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
            'images' => $images,
            'videos' => array_values(array_filter($product['videos'] ?? [], 'is_array')),
            'description' => (string) ($product['description'] ?? ''),
            'updated_at' => (string) ($product['updated_at'] ?? ''),
        ];
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
