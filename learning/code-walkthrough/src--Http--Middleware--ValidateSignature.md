# src/Http/Middleware/ValidateSignature.php — 署名検証ミドルウェア

> 出典: プロジェクト内の `src/Http/Middleware/ValidateSignature.php`。SHA-256: `49a1b05b7e1fe34017719f8f773eb99acae0354f858f656080558c8f64b54d7f`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：クエリの署名がHMACで生成した正しい署名かを判定する。
- **入力**：query
- **出力・副作用**：bool
- **他ファイルとの関係**：SignedUrl::validSignature
- **前提知識**：ミドルウェア・真正性

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Security\SignedUrl;

final class ValidateSignature
{
    public function __construct(private readonly SignedUrl $signer) {}

    /** @param array<string,mixed> $query */
    public function passes(array $query): bool
    {
        return $this->signer->validSignature($query);
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | HMAC署名の正当性を確認し、不正クエリを拒否する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L16–L17 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L10–L12)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`passes()` (L13–L17)**：単一条件をboolで返す。VerificationService内のチェック処理を分離している。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（query）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（bool）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：クエリの署名がHMACで生成した正しい署名かを判定する。
2. **入力**：query。
3. **出力**：bool。
4. **依存先**：SignedUrl::validSignature。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
