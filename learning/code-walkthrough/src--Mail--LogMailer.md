# src/Mail/LogMailer.php — 開発メールのファイル記録

> 出典: プロジェクト内の `src/Mail/LogMailer.php`。SHA-256: `2d4f6583ba3320f68c7f1783e1cafab7661cf4b7b8688a80cc42135d8339d27b`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：実メールは送らず、URLと宛先を非公開ログに残す。
- **入力**：送信先、URL、ファイルパス
- **出力・副作用**：dev-mails.logへの追記
- **他ファイルとの関係**：MailerFactory.php
- **前提知識**：ファイルI/O、LOCK_EX、chmod

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Mail;

final class LogMailer implements Mailer
{
    public function __construct(private readonly string $path) {}

    public function sendVerificationEmail(string $email, string $url): void
    {
        $line = json_encode(['timestamp' => gmdate(DATE_ATOM), 'to' => $email, 'verification_url' => $url], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write development mail log.');
        }
        @chmod($this->path, 0600);
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | ファイルへ排他付き追記または設定作成を行う。 不正状態や外部障害を例外として呼び出し元へ伝える。 |
| L16–L18 | 保持している依存オブジェクトやプロパティを参照する。他のファイルとの接続点を確認する。 |

### メソッドと引数・戻り値の関係

- **`__construct()` (L8–L9)**：必須の依存オブジェクトまたは設定値をコンストラクタ引数から受け取り、readonlyプロパティに保持する。
- **`sendVerificationEmail()` (L10–L18)**：DB上の未認証ユーザーを探し、60秒の送信枠を取得し、署名付きURLをメール配信先へ渡す。失敗時の例外を握りつぶさない。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（送信先、URL、ファイルパス）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（dev-mails.logへの追記）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：実メールは送らず、URLと宛先を非公開ログに残す。
2. **入力**：送信先、URL、ファイルパス。
3. **出力**：dev-mails.logへの追記。
4. **依存先**：MailerFactory.php。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
