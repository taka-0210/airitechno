# 厨房君「HP掲載」から新商品APIへの即時反映

## 目的

厨房君の商品編集画面で「HP掲載」を実行した直後に、rise-up.net の新商品API用スナップショットを差分更新します。

従来の5分間隔CRONは、即時同期が一時的に失敗した場合の保険として継続します。「HP確認」のリンク先は今回変更しません。

## 配置ファイル

- 同期トリガー: `/home/xsvx1007016/rise-up.net/api-sync/catalog-trigger.php`
- 既存同期本体: `/home/xsvx1007016/rise-up.net/api-sync/sync-catalog.php`
- 厨房君の変更対象: `/home/xsvx1007016/rise-up.net/public_html/rubs/item_data_manage.php`
- 実行ログ: `/home/xsvx1007016/rise-up.net/log/api-sync/catalog-sync.log`

## 厨房君側の変更箇所

`setStockListRubs()` 内にある次の2か所です。

1. `stock_list_rubs` の更新処理が成功した直後
2. `stock_list_rubs` の新規登録処理が成功した直後

それぞれ、成功メッセージと `return true;` の間に次の処理を追加します。

```php
$catalogTriggerPath = dirname(dirname(__DIR__)) . '/api-sync/catalog-trigger.php';
if (is_file($catalogTriggerPath)) {
    require_once $catalogTriggerPath;
    trigger_catalog_sync_now();
}
```

同期処理の失敗はログへ記録しますが、旧サイトへのHP掲載処理を失敗扱いにはしません。次回の5分間隔CRONで再同期されます。

## 動作の流れ

1. 厨房君で商品情報を保存
2. 「HP掲載」をクリック
3. 既存の `stock_list_rubs` を更新
4. 新商品APIの `catalog.sqlite` を差分更新
5. 新サイトが次のAPIアクセスから更新後の商品情報を取得

