# Use‑cases

Набор практических сценариев, которые часто встречаются при интеграции API.

## 1) Массовая загрузка (Batch)
```php
$result = $client
    ->batch([GetUser::class, GetOrders::class])
    ->parallel()
    ->concurrency(5)
    ->send();
```

## 2) Быстрый fan‑out (Pool)
```php
$result = $client->pool($requests, 10)->send();
```

## 3) Пагинация
```php
$result = (new ListUsers())
    ->paginate()
    ->all();

$items = $result->items();
```

## 4) Файлы и архивы
```php
$file = (new DownloadFile(10))->send()->dataOrFail();
if ($file->isArchive()) {
    $archive = $file->asArchive();
    $first = $archive->first();
}
```

## 5) Polling (лонг‑раннинг запросы)
```php
$attempts = 0;
do {
    $result = (new CheckStatus($taskId))->send()->raw();
    $attempts++;
    if ($result->isSuccess()) {
        break;
    }
    usleep(500_000);
} while ($attempts < 20);
```

Рекомендации:
- используйте `withDelay()` и `RetryConfig`, если нужна единая политика ожидания
- учитывайте `Retry-After` и ограничения rate‑limit

## 6) Мегаклиент (несколько сервисов)
```php
$result = $mega->realty()->reports()->get($id)->send();
$status = $mega->tax()->status()->check($inn)->send();
```

Сценарий: один поставщик даёт несколько API‑сервисов с разными baseUrl/auth.
Мегаклиент разделяет конфиги по сервисам и сохраняет единый вход.
