# 主要ソースファイルの全文・逐次解説

各資料には**実装ソース全文**とSHA-256ハッシュ、処理順の行番号解説、実行トレースを掲載しています。
コードの変更後は`python3 scripts/generate-walkthrough.py`を再実行し、`python3 scripts/validate-walkthrough.py`で整合性を確認してください。

| 解説資料 | 対象ソース |
|---|---|
| [public--index.md](public--index.md) | `public/index.php` |
| [public--router.md](public--router.md) | `public/router.php` |
| [src--bootstrap.md](src--bootstrap.md) | `src/bootstrap.php` |
| [src--Config.md](src--Config.md) | `src/Config.php` |
| [src--App.md](src--App.md) | `src/App.php` |
| [src--View.md](src--View.md) | `src/View.php` |
| [src--Http--Request.md](src--Http--Request.md) | `src/Http/Request.php` |
| [src--Http--Response.md](src--Http--Response.md) | `src/Http/Response.php` |
| [src--Http--Middleware--ValidateSignature.md](src--Http--Middleware--ValidateSignature.md) | `src/Http/Middleware/ValidateSignature.php` |
| [src--Http--Middleware--ValidateExpiration.md](src--Http--Middleware--ValidateExpiration.md) | `src/Http/Middleware/ValidateExpiration.php` |
| [src--Http--Middleware--RequireVerified.md](src--Http--Middleware--RequireVerified.md) | `src/Http/Middleware/RequireVerified.php` |
| [src--Security--Csrf.md](src--Security--Csrf.md) | `src/Security/Csrf.php` |
| [src--Security--SignedUrl.md](src--Security--SignedUrl.md) | `src/Security/SignedUrl.php` |
| [src--Persistence--UserRepository.md](src--Persistence--UserRepository.md) | `src/Persistence/UserRepository.php` |
| [src--Persistence--SqliteUserRepository.md](src--Persistence--SqliteUserRepository.md) | `src/Persistence/SqliteUserRepository.php` |
| [src--Persistence--Database.md](src--Persistence--Database.md) | `src/Persistence/Database.php` |
| [src--Mail--Mailer.md](src--Mail--Mailer.md) | `src/Mail/Mailer.php` |
| [src--Mail--LogMailer.md](src--Mail--LogMailer.md) | `src/Mail/LogMailer.php` |
| [src--Mail--PhpMailerAdapter.md](src--Mail--PhpMailerAdapter.md) | `src/Mail/PhpMailerAdapter.php` |
| [src--Mail--MailerFactory.md](src--Mail--MailerFactory.md) | `src/Mail/MailerFactory.php` |
| [src--Service--VerificationService.md](src--Service--VerificationService.md) | `src/Service/VerificationService.php` |
| [scripts--setup.md](scripts--setup.md) | `scripts/setup.php` |
| [scripts--show-last-link.md](scripts--show-last-link.md) | `scripts/show-last-link.php` |
