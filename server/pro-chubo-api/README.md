# pro-chubo.com 在庫API

厨房君（HYPER）を唯一の管理元とし、公開専用SQLiteスナップショットを介して本部・FCサイトへ商品JSON APIを提供します。WebユーザーはHYPER DBへ直接接続しません。

## データ更新

- 5分ごと: 更新商品の差分反映と公開対象外商品の削除照合
- 毎日3:12: 全公開商品を新しいSQLiteへ再構築し、完成後に原子的に切り替え
- 同期失敗時: 直前の正常なSQLiteを継続利用
- 掲載条件: 公開在庫。取寄売約は除外。出荷済みは30日後に除外

```text
php5.6 /home/{server-id}/pro-chubo.com/api-sync/sync-catalog.php delta
php5.6 /home/{server-id}/pro-chubo.com/api-sync/sync-catalog.php full
```

## エンドポイント

```text
GET /api/v1/catalog/categories
GET /api/v1/catalog/products?page=1&per_page=24&class1=冷機器&class2_id=123
GET /api/v1/stores/{store_id}/categories
GET /api/v1/stores/{store_id}/products?page=1&per_page=24&class1=冷機器&class2_id=123
GET /api/v1/products/{jancode}
GET /api/v1/thumb.php?id={jancode}&no=0&size=480
```

JSON APIは `Authorization: Bearer` または `X-API-Key` で認証します。店舗キーは許可された店舗だけ、本部キーは全店舗カタログへアクセスできます。

## 配置

- `public_html/api/v1/` → `/home/{server-id}/pro-chubo.com/public_html/api/v1/`
- `sync/sync-catalog.php` → `/home/{server-id}/pro-chubo.com/api-sync/sync-catalog.php`
- Git管理外の設定 → `/home/{server-id}/pro-chubo.com/api-config.php`
- 公開スナップショット → `/home/{server-id}/pro-chubo.com/api-cache/catalog.sqlite`
- サムネイルキャッシュ → `/home/{server-id}/pro-chubo.com/api-cache/thumbnails/`

APIキーの平文は設定ファイルへ保存せず、SHA-256ハッシュを登録します。
