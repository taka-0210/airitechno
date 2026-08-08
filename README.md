# アイリテクノ 商品紹介

プロ厨房ヒット新居浜店（店舗ID `265`）の商品を、pro-chubo.com APIから取得して表示する軽量PHPアプリです。

## 動作

1. APIが設定されていればAPIから商品を取得します。
2. 正常取得したレスポンスを `storage/cache/` に保存します。
3. API障害時は直前のキャッシュを使用します。
4. API未設定かつキャッシュがないローカル環境では、確認済み5商品のサンプルを表示します。

## ローカルURL

```text
http://localhost/airitechno/public/
```

## API切り替え

環境変数をWebサーバー側へ設定します。APIキーをソースやJavaScriptへ記載しないでください。

```text
PRO_CHUBO_API_BASE_URL=https://pro-chubo.com/api/v1
PRO_CHUBO_API_KEY=発行されたAPIキー
PRO_CHUBO_STORE_ID=265
```

想定する一覧API：

```text
GET /stores/265/products
Authorization: Bearer {API key}
```

レスポンスは `data` 配列、または商品配列そのものを受け付けます。

## pro-chubo.com API

`server/pro-chubo-api/` に、pro-chubo.comへ配置するPHP 5.4互換のAPIパッケージがあります。

- 大分類・中分類と商品件数
- 24件単位の商品一覧（最大60件）
- 商品詳細
- 店舗単位のAPIキー権限
- ETag・HTTPキャッシュ
- キャッシュ付き一覧用サムネイル

本番サーバーへ配置する前に、`server/pro-chubo-api/config/api-config.example.php` を参考に、公開領域外へAPI設定を作成します。
