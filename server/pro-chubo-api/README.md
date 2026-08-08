# pro-chubo.com FC商品API

pro-chubo.comの公開商品同期テーブル `stock_list_rubs` を、FCサイト向けJSON APIとして提供します。

## エンドポイント

```text
GET /api/v1/stores/{store_id}/categories
GET /api/v1/stores/{store_id}/products?page=1&per_page=24&class1=冷機器&class2_id=123
GET /api/v1/products/{jancode}
GET /api/v1/thumb.php?id={jancode}&no=0&size=480
```

JSON APIは以下のいずれかで認証します。

```text
Authorization: Bearer {API key}
X-API-Key: {API key}
```

サムネイルは元画像自体が公開画像であるため、商品番号・連番・サイズを厳格に検証したうえで認証なしで配信します。

## 配置

- `public_html/api/v1/` → `/home/{server-id}/pro-chubo.com/public_html/api/v1/`
- `config/api-config.example.php` を参考に、Git管理外の `/home/{server-id}/pro-chubo.com/api-config.php` を作成
- `/home/{server-id}/pro-chubo.com/api-cache/thumbnails/` をPHPから書き込み可能にする

APIキーの平文は設定ファイルへ保存せず、SHA-256ハッシュを登録します。
