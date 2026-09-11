# Transport & Testing — план тестирования

## Scope
- `TransportInterface`, `HttpTransport`
- `MockTransport`, `RecordingTransport`
- `MockClient`, `MockConfig`, `Fixture`, `FixtureRedactor`

## Invariants
- Тесты изолированы (без реальных HTTP).
- `MockClient::global()` перехватывает все запросы.
- `preventStrayRequests()` блокирует незамоканные вызовы.

## Unit tests
- `MockTransport` отдаёт заданные ответы.
- `RecordingTransport` сохраняет фикстуры.
- `FixtureRedactor` маскирует данные.

## Integration tests
- `client->fake()` + `assertSent/NotSent`.
- `record()`/`playback()` с фикстурами.

## Edge cases
- MissingFixtureException.
- UnmockedRequestException.

## Fixtures/Mocks
- Набор фикстур с чувствительными данными (для redactor).

## Priority
- P0: fake + preventStrayRequests.
- P1: recording/playback.
