# src/Config.php — 設定と秘密鍵の管理

> 出典: プロジェクト内の `src/Config.php`。SHA-256: `e9efd0b651b09b35e700313c04c58f77e7838aba50a0e7f740041c059ce78a2e`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：環境ファイルを解析し、検証済みの署名鍵をデコードして提供する。
- **入力**：.envと環境変数
- **出力・副作用**：設定文字列・秘密鍵のバイト列
- **他ファイルとの関係**：bootstrap.php, SignedUrl.php
- **前提知識**：.env、hex2bin、静的メソッド

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App;

final class Config
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        self::$values = [];
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                self::$values[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        return $env !== false ? $env : (self::$values[$key] ?? $default);
    }

    public static function signingKey(): string
    {
        $key = self::get('APP_KEY');
        if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
            throw new \RuntimeException('APP_KEY must be 64 hexadecimal characters; run php scripts/setup.php.');
        }
        return hex2bin($key);
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L11–L15 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L16–L20 | 条件分岐。成立時と不成立時に実行経路が分かれるため、条件式の型と境界値を確認する。 |
| L21–L25 | ホストURLや署名のフィールド形式を正規表現で検証する。 |
| L26–L30 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L31–L35 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L36–L40 | ホストURLや署名のフィールド形式を正規表現で検証する。 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L41–L44 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |

### メソッドと引数・戻り値の関係

- **`load()` (L11–L29)**：環境設定ファイルの行を読み、キーの形式を制限し、設定値を保持する。戻り値はなくConfig内の静的状態が変わる。
- **`get()` (L30–L35)**：getenvを優先して設定値を文字列として返す。OSの環境変数で.envを上書きできる。
- **`signingKey()` (L36–L44)**：APP_KEYを64桁の16進数として検証し、hex2binで32バイトのHMAC鍵に変換する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（.envと環境変数）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（設定文字列・秘密鍵のバイト列）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：環境ファイルを解析し、検証済みの署名鍵をデコードして提供する。
2. **入力**：.envと環境変数。
3. **出力**：設定文字列・秘密鍵のバイト列。
4. **依存先**：bootstrap.php, SignedUrl.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
