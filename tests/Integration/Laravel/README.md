# Проверка Laravel-интеграции

Из корня пакета:

```bash
composer install --working-dir=tests/Integration/Laravel --no-interaction
composer test --working-dir=tests/Integration/Laravel
```

Это отдельное Laravel 12 приложение с собственным lock. ApiSutra подключается через
path repository без require-dev самого пакета. Зависимости приложения устанавливаются
в `.laravel-integration/vendor` вне дерева tests, чтобы Pest не обходил циклическую
ссылку path-пакета при поиске datasets. Эта папка исключена из Git.

Проверяются discovery, HTTP, явная RequestFactory, два клиента, Artisan, два задания
в одном процессе, config:cache и повторный bootstrap. HTTP заменён MockTransport;
БД, Redis и внешний API не используются. Это проверка sync dispatch и жизненного цикла
приложения, не подтверждение Octane или распределённого queue worker.
