# scripts/setup.php — 初期セットアップ

> 出典: プロジェクト内の `scripts/setup.php`。SHA-256: `f285c5789f9b3c6066b45b6ba519e969a54248a6ccd688cff17418bf7d79e1e2`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：鍵生成とDBスキーマの作成を行う。再実行しても.envの既存鍵は保持する。
- **入力**：.env.example, schema.sql
- **出力・副作用**：.envとstorage/app.sqlite
- **他ファイルとの関係**：Config, PDO
- **前提知識**：セットアップ、秘匿情報、DB初期化

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . '/.env';
if (!is_file($envPath)) {
    $template = file_get_contents($root . '/.env.example');
    if ($template === false) {
        throw new RuntimeException('Missing .env.example');
    }
    $template = preg_replace('/^APP_KEY=$/m', 'APP_KEY=' . bin2hex(random_bytes(32)), $template);
    if (file_put_contents($envPath, $template, LOCK_EX) === false) {
        throw new RuntimeException('Unable to create .env');
    }
    @chmod($envPath, 0600);
    echo "Created .env with a random APP_KEY.\n";
} else {
    echo "Keeping existing .env and APP_KEY.\n";
}
require $root . '/src/bootstrap.php';
App\Config::signingKey();
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Missing pdo_sqlite extension. Install php-sqlite3, then rerun setup.\n");
    exit(1);
}
$path = $root . '/storage/app.sqlite';
$db = new PDO('sqlite:' . $path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));
@chmod($path, 0600);
echo "Database ready: storage/app.sqlite\n";
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L6–L10 | 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L11–L15 | ファイルへ排他付き追記または設定作成を行う。 予測困難な暗号学的乱数を生成する。 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L16–L20 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |
| L21–L25 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L26–L30 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L31–L31 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **トップレベル処理**：Web/CLIの入口から終了まで上から順に実行。宣言以外の関数呼び出し順と副作用を確認する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（.env.example, schema.sql）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（.envとstorage/app.sqlite）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：鍵生成とDBスキーマの作成を行う。再実行しても.envの既存鍵は保持する。
2. **入力**：.env.example, schema.sql。
3. **出力**：.envとstorage/app.sqlite。
4. **依存先**：Config, PDO。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
