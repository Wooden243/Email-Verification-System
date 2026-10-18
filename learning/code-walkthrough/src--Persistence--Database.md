# src/Persistence/Database.php — データベース接続

> 出典: プロジェクト内の `src/Persistence/Database.php`。SHA-256: `4a67376218a2835b7569018dd06200bd1d86e4d7c04d7044c73e17e23debf39b`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：SQLite拡張の有無・DBファイルを確認し、安全なPDO接続を返す。
- **入力**：SQLiteファイルパス
- **出力・副作用**：PDO
- **他ファイルとの関係**：public/index.php
- **前提知識**：PHP拡張とPDO

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException('pdo_sqlite is required. Install php-sqlite3 for your PHP version.');
        }
        if (!is_file($path)) {
            throw new \RuntimeException('Database missing; run php scripts/setup.php.');
        }
        $db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L16–L20 | 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L21–L25 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L26–L26 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`connect()` (L10–L26)**：pdo_sqlite拡張とDBの存在を確認してPDOを生成し、例外/フェッチ等のオプションを設定する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（SQLiteファイルパス）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（PDO）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：SQLite拡張の有無・DBファイルを確認し、安全なPDO接続を返す。
2. **入力**：SQLiteファイルパス。
3. **出力**：PDO。
4. **依存先**：public/index.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
