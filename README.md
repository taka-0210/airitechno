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
