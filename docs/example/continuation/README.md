# Пример ожидания операции

Из checkout после Composer install:

```bash
php docs/example/continuation/run.php
```

Для установленного пакета добавьте `vendor/brahmic/apisutra/` перед путём.
Ожидается `{"id":7,"cached_same_object":true,"requests":3}`.

[run.php](run.php) использует клиент учебного SDK, fake и строгие правила FinalDto.
[StartRequest](src/StartRequest.php) объявляет финальный тип и poll-запрос.
[PollRequest](src/PollRequest.php) принимает один обязательный token.
[TokenExtractor](src/TokenExtractor.php) читает его из исходного HTTP JSON;
[OperationStateResolver](src/OperationStateResolver.php) явно выбирает Pending/Ready/Failed.
[FinalDto](src/FinalDto.php) создаётся только после Ready.

Старт возвращает Pending; первый poll тоже Pending, второй Ready. Поэтому число
HTTP-вызовов равно 3 при maxAttempts = 2. Повторный await возвращает тот же DTO
без нового HTTP. Интервал равен нулю только для локального примера.

[Практика](../../guides/recipes/continuation.md) ·
[Готовность](../../reference/execution/continuation-state.md) ·
[Ожидание и ошибки](../../reference/execution/continuation-await.md).
