# scripts/show-last-link.php — 開発メールのURL表示

> 出典: プロジェクト内の `scripts/show-last-link.php`。SHA-256: `c4fbcd1e105d1faf16d58c35835e0c423ef81ca2f729e49811c5ed9b465cb688`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：開発ログの最後のレコードから認証URLを読み出す。
- **入力**：dev-mails.log
- **出力・副作用**：標準出力に検証URL
- **他ファイルとの関係**：Config, LogMailer
- **前提知識**：CLI、JSONログ、秘密情報

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';
if (App\Config::get('APP_ENV', 'local') === 'production') {
    fwrite(STDERR, "Unavailable in production.\n");
    exit(1);
}
$path = $root . '/storage/dev-mails.log';
$lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$last = $lines ? json_decode(end($lines), true) : null;
if (!is_array($last) || !isset($last['verification_url'])) {
    fwrite(STDERR, "No development emails have been logged.\n");
    exit(1);
}
echo $last['verification_url'] . "\n";
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L6–L10 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L11–L15 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L16–L17 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **トップレベル処理**：Web/CLIの入口から終了まで上から順に実行。宣言以外の関数呼び出し順と副作用を確認する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（dev-mails.log）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（標準出力に検証URL）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：開発ログの最後のレコードから認証URLを読み出す。
2. **入力**：dev-mails.log。
3. **出力**：標準出力に検証URL。
4. **依存先**：Config, LogMailer。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
