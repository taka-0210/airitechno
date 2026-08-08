<?php
declare(strict_types=1);

final class ProChuboApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 8
    ) {
    }

    public function products(string $storeId, array $query = []): array
    {
        return $this->productsPage($storeId, $query)['data'];
    }

    public function productsPage(string $storeId, array $query = []): array
    {
        $url = $this->baseUrl . '/stores/' . rawurlencode($storeId) . '/products';
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $decoded = json_decode($this->request($url), true, 512, JSON_THROW_ON_ERROR);
        $products = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        if (!is_array($products)) {
            throw new RuntimeException('商品APIのレスポンス形式が正しくありません。');
        }
        return [
            'data' => array_values(array_filter($products, 'is_array')),
            'meta' => isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : [],
        ];
    }

    public function categories(string $storeId): array
    {
        $url = $this->baseUrl . '/stores/' . rawurlencode($storeId) . '/categories';
        $decoded = json_decode($this->request($url), true, 512, JSON_THROW_ON_ERROR);
        $categories = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        if (!is_array($categories)) {
            throw new RuntimeException('分類APIのレスポンス形式が正しくありません。');
        }
        return array_values(array_filter($categories, 'is_array'));
    }

    public function product(string $productId): array
    {
        $url = $this->baseUrl . '/products/' . rawurlencode($productId);
        $decoded = json_decode($this->request($url), true, 512, JSON_THROW_ON_ERROR);
        $product = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        if (!is_array($product)) {
            throw new RuntimeException('商品詳細APIのレスポンス形式が正しくありません。');
        }
        return $product;
    }

    private function request(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL拡張が必要です。');
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'X-API-Key: ' . $this->apiKey,
                'User-Agent: AiritechnoProductCatalogue/1.0',
            ],
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('商品APIへの接続に失敗しました。HTTP ' . $status . ($error !== '' ? ' / ' . $error : ''));
        }
        return $body;
    }
}
