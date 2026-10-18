# src/Mail/MailerFactory.php — メール配信手段の選択

> 出典: プロジェクト内の `src/Mail/MailerFactory.php`。SHA-256: `c79cc8c9234250b099120afe21618d40ee18be0c7213285a12e4aaf9b33bc76c`。この文書には**生成時点のソースコード全文**を一字も省略せず掲載しています。

## ① ファイル概要

- **役割**：開発時のログとSMTP送信を環境変数で切り替え、本番でのログ方式を禁止する。
- **入力**：APP_ENV、MAIL_TRANSPORT
- **出力・副作用**：Mailerオブジェクト
- **他ファイルとの関係**：LogMailer, PhpMailerAdapter
- **前提知識**：Factoryパターン、環境変数

## ② ソースコード全文（省略なし）

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config;

final class MailerFactory
{
    public static function make(string $root): Mailer
    {
        $transport = Config::get('MAIL_TRANSPORT', 'log');
        if ($transport === 'smtp') {
            return new PhpMailerAdapter();
        }
        if ($transport === 'log' && Config::get('APP_ENV', 'local') !== 'production') {
            return new LogMailer($root . '/storage/dev-mails.log');
        }
        throw new \RuntimeException('Invalid mail transport for this environment.');
    }
}
```

## ③ 上から下への逐次解説（行・コードブロック単位）

以下の`L`は、このファイルの実際の行番号です。コードを5行以下の小区間に分割して、上から順に目的・値の流れ・防御すべき条件を解説します。詳しい構文の意味は`learning/02_KEY_CONCEPTS.md`を参照してください。

| 行 | 意味・注意点 |
|---|---|
| L1–L5 | 名前空間・型・依存するクラスの宣言。クラスの責務と参照先を確認する。 |
| L6–L10 | メソッド定義と引数の宣言。型宣言と戻り値型を読み、呼び出し側との契約を確認する。 |
| L11–L15 | この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L16–L20 | 不正状態や外部障害を例外として呼び出し元へ伝える。 この行で関数の処理を終了し、値を呼び出し元へ戻す。 |
| L21–L21 | 構文上の区切り、または前後の処理を連結する部分。上段の全文コードと併せて読む。 |

### メソッドと引数・戻り値の関係

- **`make()` (L10–L21)**：RFC3986でクエリを組み立て、APP_URLと/verify/emailへ連結してメールに載せるURLを返す。

`return`は呼出し元へ制御を返します。`void`のメソッドでは通常戻り値がなく、DB更新・送信・セッション操作などの副作用が主目的です。メソッドの引数型、条件分岐、例外伝播を、上の全コードと行番号を合わせて確認してください。

## ④ 実行トレース

例：上記の入力（APP_ENV、MAIL_TRANSPORT）が渡された場合、全文コードを上から辿り、条件分岐と戻り値（Mailerオブジェクト）を記録する。

## ⑤ 最重要部分の再確認

1. **責務**：開発時のログとSMTP送信を環境変数で切り替え、本番でのログ方式を禁止する。
2. **入力**：APP_ENV、MAIL_TRANSPORT。
3. **出力**：Mailerオブジェクト。
4. **依存先**：LogMailer, PhpMailerAdapter。
5. **副作用**：データ更新・HTTPレスポンス・ログ・セッション操作の有無を確認する。
6. **失敗経路**：条件不成立、例外、入力の型の不一致がどう処理されるか確認する。
7. **セキュリティ**：信頼できない外部値をどこで検証しているか確認する。
8. **変更時の影響**：このファイルを変更したら関連テストを実行し、学習資料を再生成する。
