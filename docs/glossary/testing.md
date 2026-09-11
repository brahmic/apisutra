# Тестирование

## TransportInterface
Интерфейс абстракции HTTP-транспорта. Метод send(PreparedRequest): ProviderResponse. Реализации: HttpTransport (production), MockTransport (testing). Client получает транспорт через DI — нет static state.

## HttpTransport
Production-реализация TransportInterface. Выполняет реальные HTTP-запросы через Guzzle/PSR-18 клиент.

## MockTransport
Testing-реализация TransportInterface. Возвращает mock-ответы, записывает вызовы для assertions. Поддерживает closure, URL-pattern, sequence.

## MockClient
Фасад для настройки тестирования. Статические методы: global(mocks) — установить глобальный MockTransport для перехвата запросов, destroyGlobal() — очистить между тестами. Удобен для тестового bootstrap-слоя, но не обязателен для каждого SDK.

## MockConfig
Конфигурация тестирования. Методы: throwOnMissingFixtures() — включить строгую реакцию на отсутствие fixtures при их разрешении; setFixturePath(path) — путь к fixtures.

## fake()
Метод AbstractClient для регистрации mock-ответов. Принимает массив [RequestClass::class => data/MockResponse/Closure]. Поддерживает URL-pattern ('api.example.com/*'). Wildcard '*' для всех незамоканных.

## MockResponse
Value Object для mock-ответа. Методы: make(data, status, headers), success(), notFound(), serverError(), rateLimited(), sequence() для последовательных ответов.

## assertSent()
Метод для проверки вызова запроса в тестах. Параметры: класс запроса, times (количество), callback для проверки параметров.

## assertNothingSent()
Метод для проверки отсутствия вызовов. Проверяет что ни один запрос не был отправлен.

## preventStrayRequests()
Метод AbstractClient. Включает режим, при котором незамоканные запросы бросают исключение. Для защиты тестов от случайных реальных запросов.

## record() / playback()
Методы для записи и воспроизведения fixtures. record(path) — сохранить реальные ответы. playback(path) — воспроизвести в тестах.

## Fixture
Базовый класс для кастомных fixtures с redacting. Методы: defineName(), defineSensitiveHeaders(), defineSensitiveJsonParameters(), defineSensitiveRegexPatterns(). Для скрытия чувствительных данных в записанных ответах.

## LiveEnvLoader
Namespace: `Brahmic\ApiSutra\Testing\LiveEnvLoader`. Загрузка переменных из .env-файла в putenv/ENV/SERVER для live-тестов. Методы: `load(string $path)` — загрузка из файла; `loadForTests(string $testsDir)` — загрузка .env.live.local из корня пакета (удобно в tests/bootstrap.php). Не перезаписывает уже заданные переменные. Без зависимости от dotenv.

## LivePolling
Namespace: `Brahmic\ApiSutra\Testing\LivePolling`. Polling-хелпер для async flow в live-тестах. Метод `waitUntil(callable $fetch, callable $isReady, int $timeoutSeconds, int $intervalMilliseconds, ?string $timeoutMessage): mixed` — повторяет $fetch до тех пор, пока $isReady не вернёт true или не истечёт таймаут. При таймауте бросает RuntimeException с информацией о последнем значении. Вызовы внутри callback должны использовать `->withoutCache()`.

## LiveResultAssertions
Namespace: `Brahmic\ApiSutra\Testing\LiveResultAssertions`. Assertion-хелперы для live-тестов. Методы: `assertSuccess(ResolvedResultInterface $resolved, string $operation)` — бросает RuntimeException, если результат не помечен как success на уровне result-layer; `assertDataInstanceOf(ResolvedResultInterface $resolved, string $expectedClass, string $operation)` — проверяет technical success и тип `data()`. Эти helper-методы не выполняют business-оценку provider-ответа. Framework-agnostic.

## Pool (пул запросов)
Механизм массового выполнения запросов с контролем concurrency. Метод pool(requests, concurrency). Использует generator для lazy loading. Handlers для success/error.
