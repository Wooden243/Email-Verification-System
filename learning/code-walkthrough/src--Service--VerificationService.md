# src/Service/VerificationService.php — 認証の中心サービス

> 出典: プロジェクト内の `src/Service/VerificationService.php`。SHA-256: `1219402fa779004c854fd3bc35cb40ec9ee52a343384ec98dc3d7c954c40e760`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：登録/再送の署名生成と送信、署名・期限・現登録メールの検証を統合する。
- **入力**：ユーザーID、now、query
- **出力・副作用**：送信のbool、検証結果文字列
- **他ファイルとの関係**：UserRepository, Mailer, SignedUrl, ValidateSignature, ValidateExpiration
- **前提知識**：サービス層・メール送信・検証フロー

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\Middleware\ValidateExpiration;
use App\Http\Middleware\ValidateSignature;
use App\Mail\Mailer;
use App\Persistence\UserRepository;
use App\Security\SignedUrl;

final class VerificationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly SignedUrl $urls
    ) {}

    public function sendVerificationEmail(int $id, int $now): bool
    {
        $user = $this->users->findById($id);
        if ($user === null || (int) $user['email_verified'] === 1) {
            return false;
        }
        if (!$this->users->claimSendSlot($id, $now, 60)) {
            return false;
        }
        $url = $this->urls->make($id, (string) $user['email'], $now);
        $this->mailer->sendVerificationEmail((string) $user['email'], $url);
        return true;
    }

    /** @param array<string,mixed> $query Returns one of: invalid, expired, mismatch, verified. */
    public function verify(array $query, int $now): string
    {
        if (!(new ValidateSignature($this->urls))->passes($query)) {
            return 'invalid';
        }
        if (!(new ValidateExpiration())->passes($query, $now)) {
            return 'expired';
        }
        return $this->users->markVerifiedIfEmailMatches((int) $query['id'], $query['user'])
            ? 'verified' : 'mismatch';
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L16–L20 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L21–L25 | リポジトリ経由で現在のユーザー情報を読み取る。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L26–L30 | 宛先と署名付きURLをMailerの実装へ渡す。 発行時刻を使って30分間の署名付きリンクを構築する。 再送を許可してよいかDBの条件付き更新で判定する。 |
| L31–L35 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L36–L40 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L41–L45 | 現メールとの一致を満たす場合だけ認証状態を更新する。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L46–L46 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L14–L19)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`sendVerificationEmail()` (L20–L34)**：DB上の未認証ユーザーを探し、60秒の送信枠を取得し、署名付きURLをメール配信先へ渡す。失敗時の例外を握りつぶさない。
- **`verify()` (L35–L46)**：署名検証→期限検証→現在メール照合とDB更新の順番で実行する。返り値はinvalid/expired/mismatch/verified。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：`sendVerificationEmail(7, 1790000000)`で送信枠が取れればMailerが1回呼ばれる。`verify(query, 1790001800)`は署名が正しくても`expired`を返す。

## ⑤ 最重要部分の再確認

1. **責務**：登録/再送の署名生成と送信、署名・期限・現登録メールの検証を統合する。
2. **入力**：ユーザーID、now、query。
3. **出力**：送信のbool、検証結果文字列。
4. **依存先**：UserRepository, Mailer, SignedUrl, ValidateSignature, ValidateExpiration。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
