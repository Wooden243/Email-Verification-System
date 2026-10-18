# src/Http/Response.php — HTTPレスポンスの表現

> 出典: プロジェクト内の `src/Http/Response.php`。SHA-256: `a4144321fb65389e09daa2f0c3282c7b32d92f5a59e4634c48ec5ba71559db21`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：HTTP状態コード、ヘッダー、HTML本文をまとめる。
- **入力**：status, body, headers
- **出力・副作用**：HTTP送信、303リダイレクト
- **他ファイルとの関係**：App.php
- **前提知識**：HTTP状態コードとLocation

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {}

    public static function redirect(string $path): self
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('Only internal redirects are allowed.');
        }
        return new self(303, '', ['Location' => $path]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L16–L20 | 不正状態や外部障害を例外として呼び出し元へ伝える。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L21–L25 | HTTPの状態コードを設定する。 |
| L26–L30 | ブラウザに送るHTTPヘッダーを設定する。 |
| L31–L31 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L9–L14)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`redirect()` (L15–L22)**：内部パスだけを許可してLocationヘッダー付き303レスポンスを返す。外部URLによるオープンリダイレクトを防ぐ。
- **`send()` (L23–L31)**：status/headers/bodyをPHPのHTTP出力関数へ渡す。実際にレスポンスが利用者へ流れる境界。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（status, body, headers）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（HTTP送信、303リダイレクト）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：HTTP状態コード、ヘッダー、HTML本文をまとめる。
2. **入力**：status, body, headers。
3. **出力**：HTTP送信、303リダイレクト。
4. **依存先**：App.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
