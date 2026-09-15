<p align="center">
  <img src="docs/assets/apisutra-logo.png" alt="Логотип ApiSutra" width="233">
</p>

# ApiSutra

[![Tests](https://github.com/brahmic/apisutra/actions/workflows/tests.yml/badge.svg?branch=master&event=push)](https://github.com/brahmic/apisutra/actions/workflows/tests.yml)
[![Docs CI](https://github.com/brahmic/apisutra/actions/workflows/docs.yml/badge.svg?branch=master&event=push)](https://github.com/brahmic/apisutra/actions/workflows/docs.yml)
[![Documentation](https://img.shields.io/badge/docs-read-2563eb)](docs/README.md)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](composer.json)
[![Packagist](https://img.shields.io/packagist/vpre/brahmic/apisutra)](https://packagist.org/packages/brahmic/apisutra)

ApiSutra — PHP-пакет для создания SDK внешних API. Вы описываете операции, DTO
и правила протокола, а пакет выполняет HTTP-запросы, преобразует ответы и даёт
общий API результатов, ошибок, повторных попыток и пагинации.

## Начать использование

Нужен PHP 8.4+ и Composer. Ядро работает без приложения Laravel.

```bash
composer require "brahmic/apisutra:^0.2@alpha"
php vendor/brahmic/apisutra/docs/example/sdk/run.php
```

Команда запускает [учебный SDK](docs/example/sdk/README.md) с локальными ответами.
[Quickstart](docs/guides/quickstart.md) объясняет его конфигурацию, транспорт, запрос и DTO.
Для настоящего HTTP требуется [транспорт с его зависимостями](docs/reference/execution/transport.md).

## Выбрать задачу

- [Создать новый SDK](docs/start/create-sdk.md) — от анализа API до готовой операции и покрытия.
- [Добавить операцию](docs/start/add-operation.md) в существующий SDK.
- [Описать DTO](docs/start/describe-dto.md) атрибутами или внешними правилами.
- [Использовать готовый SDK](docs/start/use-sdk.md) в приложении.
- [Разобрать ошибку](docs/start/diagnose.md).

Все маршруты, практические руководства и справочники — в [документации](docs/README.md).
Задание ИИ-агенту можно оформить через [короткую точку входа](docs/start/agent.md):
она использует те же маршруты и контракты.

## Возможности

- [Декларативные HTTP-запросы](docs/reference/request/declaration.md), ресурсы и версии сервисов.
- [Plain DTO и внешние правила](docs/reference/dto/field-rules.md): строгие типы,
  вложенные формы, сохранение неизвестных данных в выбранном поле, например `_extra`.
- [Атрибуты DTO](docs/reference/attributes/hydration.md), casts, коллекции и профили.
- [Auth](docs/reference/auth/README.md), [retry и квоты](docs/reference/execution/README.md),
  кеш ответов, общий дедлайн, пагинация, batch и pool.
- [Ожидание операции провайдера](docs/reference/execution/continuation-state.md)
  с явными Pending/Ready/Failed; promise API не гарантирует неблокирующий I/O.
- [Файлы](docs/reference/files/README.md), [расширения](docs/reference/extensions/README.md),
  [mock и фикстуры](docs/reference/testing/README.md), [Laravel](docs/guides/integration/laravel.md).

## Версии и разработка ApiSutra

Перед обновлением прочитайте [миграцию](docs/migration/README.md) и
[историю изменений](CHANEGLOG.md). Лицензия — [MIT](LICENSE).

Для изменения самого пакета откройте
[CONTRIBUTING в репозитории](https://github.com/brahmic/apisutra/blob/master/CONTRIBUTING.md).
Инструкции по src, тестам, CI и выпуску находятся отдельно от документации пользователя.
