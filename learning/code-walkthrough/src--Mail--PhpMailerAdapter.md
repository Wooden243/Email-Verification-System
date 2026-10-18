# src/Mail/PhpMailerAdapter.php — PHPMailerによる実メール送信

> 出典: プロジェクト内の `src/Mail/PhpMailerAdapter.php`。SHA-256: `53329b7073ae8d0db0e639de5ac56175bea95627d2d43b23bedde00d8ed44ca8`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：SMTPの接続・認証・送信元/宛先・本文を設定し、認証URLを実メールとして送る。
- **入力**：SMTP設定、宛先、URL
- **出力・副作用**：SMTP送信（失敗時は例外）
- **他ファイルとの関係**：Composer, PHPMailer, MailerFactory
- **前提知識**：SMTP、外部ライブラリ、TLS

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config;
use PHPMailer\PHPMailer\PHPMailer;

final class PhpMailerAdapter implements Mailer
{
    public function sendVerificationEmail(string $email, string $url): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer missing; run composer install.');
        }
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = Config::get('SMTP_HOST');
        $mailer->Port = (int) Config::get('SMTP_PORT', '587');
        $mailer->SMTPAuth = true;
        $mailer->Username = Config::get('SMTP_USERNAME');
        $mailer->Password = Config::get('SMTP_PASSWORD');
        $mailer->SMTPSecure = Config::get('SMTP_ENCRYPTION', 'tls');
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(Config::get('MAIL_FROM_ADDRESS'), Config::get('MAIL_FROM_NAME', 'Email Verification System'));
        $mailer->addAddress($email);
        $mailer->Subject = 'Verify your email address';
        $mailer->Body = "Open the following link within 30 minutes to verify your email address:\n\n" . $url . "\n\nIf you did not register, ignore this email.";
        $mailer->send();
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L11–L15 | 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L16–L20 | PHPMailerオブジェクトを生成してSMTPで送信する。 PHP標準mail関数ではなくSMTPモードを指定する。 |
| L21–L25 | 変数への代入を行う。右辺で作った値を後続処理に受け渡す。 |
| L26–L30 | 検証メールの送信先を指定する。 実際のメール配送要求を行う。送達完了とは別。 |
| L31–L31 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`sendVerificationEmail()` (L11–L31)**：DB上の未認証ユーザーを探し、60秒の送信枠を取得し、署名付きURLをメール配信先へ渡す。失敗時の例外を握りつぶさない。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：`sendVerificationEmail("alice@example.com", "https://example.com/verify/email?...")`はSMTP認証済みサーバーへ送信要求し、失敗時は例外が送信元に伝わる。

## ⑤ 最重要部分の再確認

1. **責務**：SMTPの接続・認証・送信元/宛先・本文を設定し、認証URLを実メールとして送る。
2. **入力**：SMTP設定、宛先、URL。
3. **出力**：SMTP送信（失敗時は例外）。
4. **依存先**：Composer, PHPMailer, MailerFactory。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
