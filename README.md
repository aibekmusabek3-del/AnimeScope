# AnimeScope 3.1 — Shikimori

Полный проект Laravel + React. PHP 8.2+, Composer, Node.js.

В PowerShell: `powershell -NoProfile -ExecutionPolicy Bypass -File .\setup.ps1`

Открыть http://127.0.0.1:8000. Для тестов: `php vendor/bin/phpunit`, `npm test`. Для проверки API: `php artisan anime:check`.

SQLite хранит аккаунты и друзей. Для доступа друга нужен общий сервер и приватная сеть, например Tailscale. Видео не скачиваются; совместный плеер требует разрешённый прямой URL видео.

Не удаляйте database/database.sqlite и .env при обновлении.
