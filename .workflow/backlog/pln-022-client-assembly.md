# Выделить внутреннюю сборку клиента

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-13
- Статус: отложен

## Отложено по поручению владельца

2026-09-13: до востребования пользователем. Реализация не начата; план сохраняет
архитектурное направление и подготовительные материалы, но не задаёт текущую очередь работ.
Возобновлять только по явному запросу пользователя. Перед возобновлением сверить
объём, стоимость и открытые продуктовые вопросы с
[итоговой позицией](../discussion/dsc-004-client-policies-and-assembly.md#итоговая-позиция-и-отложенная-реализация--2026-09-13).

## Проблема и принятый объём

AbstractClient совмещает исполнение с правилами создания/соединения зависимостей.
Внутренняя декомпозиция поддержана владельцем в
[дискуссии](../discussion/dsc-004-client-policies-and-assembly.md).
[Аудит N6–N7](../audit/aud-004-policies-admission-assembly/aud-004-readme.md)
и [проект сборщика](../audit/aud-004-policies-admission-assembly/assembly-design.md)
задают инварианты и последовательность.

Выделить внутренний ClientAssemblyFactory; сохранить явные поэтапные присваивания
в AbstractClient. Не вводить публичную фабрику, новый обязательный constructor argument,
ClientComponents/ClientDependencies, resolver административных настроек или DI framework.
Поведение send, auth, cache, DTO и testing API не изменяется.

## Шаги реализации

1. Добавить регрессионный сценарий сборки: порядок регистрации casts/extensions,
   CacheAware auth, global mock и настройки response factory; callbacks наблюдают
   те же уже инициализированные части клиента, что и до переноса.
2. Добавить Core/Assembly/ClientAssemblyFactory. Перенести правила metadata cache,
   регистрации, auth cache, создания Pipeline/RetryHandler и result factories;
   оставить простой порядок присваиваний и выбор global mock в клиенте.
3. Перевести buildPipeline/rebuildPipeline на фабрику. Не повторять полную сборку
   при fake/record/playback, не вызывать повторно регистрацию extensions или setCache.
4. Проверить шесть независимых экземпляров и явно переданные общие зависимости:
   нет случайного глобального состояния, owner текущего клиента передан правильно.
5. Обновить техническое описание сборки в docs/technical и changelog, без ссылок
   на workflow в публичном тексте. Примеры создания клиента сохраняют прежний вид.

Основные файлы: src/Core/AbstractClient.php, новый src/Core/Assembly/ClientAssemblyFactory.php,
src/Traits/TestingClientTrait.php только при необходимой адаптации делегирования;
профильные tests/Unit/Core, tests/Stubs/Core и docs/technical.
Не включать сюда перенос tooling, сужение DTO-контекста или переработку интерфейсов.

## Критерии и проверки

- Старые named/positional constructor arguments и пользовательские subclasses работают.
- Граф serializer/hydrator/registries и все переданные зависимости сохраняют identity.
- Смена transport реально направляет HTTP в новый transport; квота limiter не сбрасывается,
  callbacks не повторяются, traceId и auth owner соответствуют прежним публичным путям.
- Пользовательские response factories и ленивые сервисы сохраняют поведение.
- Сначала профильные Core/RateLimit/Auth/Testing тесты, затем `composer test`, `composer lint`,
  `composer analyse`, `composer check-docs`, `composer check-package`: меняется центральная сборка.
  Сохранить фактические команды/результаты, не расширять PHPStan baseline без причины.

## Зависимости и совместимость

Можно выполнять независимо от 023/024. Вопросы В1–В4 не блокируют этот рефакторинг.
После [рецензии](../audit/aud-004-policies-admission-assembly/peer-review.md) предлагался
порядок 023 → 024 → 022; после отложения это не активная очередь работ.
Ценность сборщика для сопровождения и согласованное направление сохраняются.
Новых runtime-зависимостей нет. Завершение — критерии подтверждены тестами, документация
и changelog обновлены, план перенесён в completed. План не разрешает менять продуктовый
контракт при обнаружении расхождения: такое изменение нужно вынести отдельно.
