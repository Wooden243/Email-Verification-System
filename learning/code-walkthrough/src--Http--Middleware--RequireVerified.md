# src/Http/Middleware/RequireVerified.php — 認証済みページの保護

> 出典: プロジェクト内の `src/Http/Middleware/RequireVerified.php`。SHA-256: `370050fb830d13a8f2f91802abef2f5f33775503067f8e1ffac2fa0f0234a1ef`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：未ログインならログインへ、未認証なら再送へ、それ以外は通す。
- **入力**：DBから得たユーザー情報
- **出力・副作用**：Responseまたはnull
- **他ファイルとの関係**：App::handle
- **前提知識**：認可・分岐

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response;

final class RequireVerified
{
    /** @param array<string,mixed>|null $user */
    public function check(?array $user): ?Response
    {
        if ($user === null) {
            return Response::redirect('/login');
        }
        return (int) $user['email_verified'] === 1 ? null : Response::redirect('/verify/resend');
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | 処理を終了してHTTP 303による移動指示を返す。 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L16–L18 | 302ではなく303を使い、POST後の再取得をGETにする。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |

### メソッドと引数・戻り値の関係

- **`check()` (L11–L18)**：nullならログインへ、未認証なら再送へ303で転送。認証済みならnullで処理続行を許可する。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：`$user=null`は`/login`、`["email_verified"=>0]`は`/verify/resend`、`["email_verified"=>1]`は`null`。

## ⑤ 最重要部分の再確認

1. **責務**：未ログインならログインへ、未認証なら再送へ、それ以外は通す。
2. **入力**：DBから得たユーザー情報。
3. **出力**：Responseまたはnull。
4. **依存先**：App::handle。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
