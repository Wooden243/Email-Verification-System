# src/Http/Request.php — HTTPリクエストの表現

> 出典: プロジェクト内の `src/Http/Request.php`。SHA-256: `9c126c3b6d5b99ec5740f0cc3503d2983b68267a5c8bfbe969b0d2b129808014`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：PHPのスーパーグローバルからmethod/path/query/formを抽出する。
- **入力**：$_SERVER, $_GET, $_POST
- **出力・副作用**：Requestオブジェクト
- **他ファイルとの関係**：App.php
- **前提知識**：HTTPリクエストと値オブジェクト

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $form */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $form = []
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', is_string($path) ? $path : '/', $_GET, $_POST);
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | プロパティの宣言。readonlyにより初期化後の参照の差し替えを防ぐ。 |
| L16–L20 | URLクエリの入力。信頼せず検証してから使う。 フォーム入力。クライアント側の入力制約だけに依存しない。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L21–L21 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L9–L15)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`fromGlobals()` (L16–L21)**：$_SERVER、$_GET、$_POSTを読み、解析済みパスをRequestのプロパティへ入れる。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（$_SERVER, $_GET, $_POST）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（Requestオブジェクト）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：PHPのスーパーグローバルからmethod/path/query/formを抽出する。
2. **入力**：$_SERVER, $_GET, $_POST。
3. **出力**：Requestオブジェクト。
4. **依存先**：App.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
