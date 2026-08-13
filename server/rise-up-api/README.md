# 厨房君 商品API（rise-up.net）

厨房君（HYPER）を唯一の管理元とし、`rise-up.net`から本部・FC・加盟店サイトへ商品JSON APIを提供します。公開サイトは厨房君DBへ直接接続せず、公開専用SQLiteスナップショットだけを参照します。

## 構成

```text
/home/{server-id}/rise-up.net/
├─ public_html/
│  ├─ rubs/                         厨房君本体（変更しない）
│  └─ api/v1/                       外部公開するAPI
├─ api-sync/sync-catalog.php        厨房君DBからの同期処理
├─ api-config.php                   Git管理外の設定
└─ api-cache/
   ├─ catalog.sqlite                公開用スナップショット
   └─ thumbnails/                   サムネイルキャッシュ
```

商品動画は `/public_html/rubs/img/item_movie/{jancode}.mp4` へ集約します。既存の`pro-chubo.com`側の動画は、同梱の`sync/migrate-videos.php`で削除せずコピーできます。

商品APIの`videos`には、商品番号に紐づく個体動画だけを`type: individual`付きで返します。型式マスターの`movie_url`は公開APIへ出力しません。厨房君で個体動画を登録し、商品を「HP掲載」にすると、その処理から呼ばれる即時差分同期後に新サイトへ商品情報が反映されます。個体動画ファイルが存在する場合は、同じ商品APIレスポンスで動画も表示されます。5分間隔のCRONは即時同期失敗時の保険です。

## 配置

- `public_html/api/v1/` → `/home/{server-id}/rise-up.net/public_html/api/v1/`
- `sync/sync-catalog.php` → `/home/{server-id}/rise-up.net/api-sync/sync-catalog.php`
- `config/api-config.example.php`を参考に、`/home/{server-id}/rise-up.net/api-config.php`を作成
- `api-cache/`はWeb公開領域外に作成し、同期処理とAPI実行ユーザーだけが読み書きできるようにする
- APIキーは平文保存せず、SHA-256ハッシュだけを設定する

厨房君本体の`/public_html/rubs/`は変更しません。

## エンドポイント

```text
GET https://rise-up.net/api/v1/catalog/categories
GET https://rise-up.net/api/v1/catalog/products?page=1&per_page=24&class1=冷機器&class2_id=123
GET https://rise-up.net/api/v1/stores/{store_id}/categories
GET https://rise-up.net/api/v1/stores/{store_id}/products?page=1&per_page=24&class1=冷機器&class2_id=123
GET https://rise-up.net/api/v1/products/{jancode}
GET https://rise-up.net/api/v1/thumb.php?id={jancode}&no=0&size=480
```

JSON APIは`Authorization: Bearer`または`X-API-Key`で認証します。店舗キーは許可店舗だけ、本部キーは全店舗カタログへアクセスできます。

## データ更新

- 5分ごと：更新商品の差分反映と公開対象外商品の削除照合
- 毎日3:12：全公開商品を新しいSQLiteへ再構築し、完成後に原子的に切り替え
- 同期失敗時：直前の正常なSQLiteを継続利用
- 掲載条件：公開在庫。取寄売約は除外。出荷済みは30日後に除外

PHP 7.4 CLIの実際のパスはサーバー管理画面またはSEへ確認してからcronへ登録します。

```text
php7.4 /home/{server-id}/rise-up.net/api-sync/sync-catalog.php delta
php7.4 /home/{server-id}/rise-up.net/api-sync/sync-catalog.php full
```

## 動画移行

最初にドライランで件数を確認します。

```text
php7.4 /home/{server-id}/rise-up.net/api-sync/migrate-videos.php
```

確認後に`--copy`を付けてコピーします。既存ファイルは上書きせず、元ファイルも削除しません。

```text
php7.4 /home/{server-id}/rise-up.net/api-sync/migrate-videos.php --copy
```

## 切替手順

1. `rise-up.net`へAPI一式とGit管理外設定を配置する
2. 動画をドライラン後にコピーする
3. 全件同期を実行して商品件数を確認する
4. 店舗ID`265`、全店、カテゴリー、商品詳細、画像、動画をテストする
5. 新サイトのAPI接続先を`https://rise-up.net/api/v1`へ変更する
6. 一定期間は`pro-chubo.com`側の旧APIと動画を残す
7. 利用サイトの切替完了後に旧APIの停止を別途判断する

GitHubへのPushとサーバー配置は別作業です。APIの配置、cron登録、新サイトの接続先変更は段階的に実施してください。
