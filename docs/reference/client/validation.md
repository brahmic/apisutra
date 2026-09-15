# Валидация входа запроса и DTO

## Валидация
Когда нужно локально проверить DTO и получить читаемые ошибки.
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Label;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;

#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

Валидация работает через атрибуты `#[Validate]` и `#[Label]`
и опирается на Laravel Validation (`Illuminate\Contracts\Validation\Factory`).

## Где применяется
- **Запросы** — до отправки HTTP
- **DTO** — при вызове `validate()` или `isValid()`

## Атрибуты
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Label;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;

#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

## Как подключается валидатор

В Laravel доступный `validator` определяется автоматически. Повторно передавать
фабрику в ClientConfig не нужно. Запросы и DTO без `#[Validate]` не обращаются к
фабрике вообще; обычное ядро и custom preflight работают без Illuminate.

Для объявленных `#[Validate]` нужна совместимая
`Illuminate\Contracts\Validation\Factory`. Если её нет или provider возвращает
неподходящий объект, SDK сообщает `ConfigurationException`. В pipeline это
`configuration_error` до HTTP. Отсутствие движка больше не считается успешной
проверкой. Новых обязательных настроек или strict-флагов нет.

### Приоритет фабрики

| Ситуация | Источник |
| --- | --- |
| Отправка запроса | `containerProvider` из конфигурации выполняющего клиента. |
| Ручной `validate()`/`isValid()`/`errors()` привязанного запроса | `containerProvider` уже привязанного клиента. |
| Явный `provider:` в `Validator::check()`/`validateOrThrow()` | Переданный provider, выше привязки запроса и общего bootstrap. |
| Provider не задан | `Validator::useFactory()`, затем глобальный provider / auto-detect. |

Явный provider используется без подстановки глобальной фабрики, даже если его
`validatorFactory()` вернул null. В этом случае настройте фабрику именно в выбранном
provider. Несколько клиентов могут иметь разные правила и сообщения: SDK не
записывает их фабрики в глобальное состояние.

Непривязанный запрос не запускает auto-resolve клиента ради ручной проверки.
Самостоятельный DTO использует общий bootstrap или явно переданный provider;
он не запоминает клиента, от которого был получен. Проверка DTO после hydrate
автоматически не запускается.

### Standalone

Для Laravel-правил можно подключить компоненты Illuminate Validation и Translation
без приложения Laravel и настроить общую фабрику при запуске приложения:

```php
use Brahmic\ApiSutra\VO\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

$factory = new Factory(new Translator(new ArrayLoader(), 'ru'));
// При необходимости добавьте переводы и собственные правила провайдера.
Validator::useFactory($factory);
```

Установка пакетов сама по себе не создаёт настроенный validator: для custom rules,
переводов и правил с внешними зависимостями нужна соответствующая настройка фабрики.
При отдельных контейнерах клиентов предоставляйте фабрику через их
`ContainerProviderInterface::validatorFactory()`.

Для отдельной проверки DTO с другим provider:

```php
use Brahmic\ApiSutra\VO\Validation\Validator;

// $dto — проверяемый DTO, $provider — настроенный ContainerProviderInterface.
$validation = Validator::check($dto, provider: $provider);
Validator::validateOrThrow($dto, provider: $provider);
```

`Validator::resetFactory()` явно очищает только общую фабрику, установленную
через `useFactory()`. После сброса снова применяется глобальный provider/auto-detect.
Это полезно при завершении bootstrap-контекста или в тестах; переключение обычных
клиентов не требует reset. `ContainerProviderRegistry::reset()` имеет собственную
область и не очищает эту фабрику.

## Валидация запросов
При ошибке:
- запрос **не отправляется**
- формируется `ExecutionResult` со статусом FAILED
- `validationErrors` содержит список `ValidationError`

## Custom preflight-валидация

Для проверок, не покрываемых `#[Validate]` (файлы, кросс-полевая логика, preflight до сериализации),
реализуйте `CustomValidatableRequestInterface`:

```php
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Files\FileInput;

final class DocumentUploadRequest extends AbstractRequest implements CustomValidatableRequestInterface
{
    public function __construct(
        #[File]
        public ?FileInput $document = null,
    ) {}

    public function validateCustom(): array
    {
        if ($this->document === null) {
            return [];
        }
        $errors = [];
        // проверка размера, mime-type, повреждённости и т.д.
        if (!$this->isDocumentValid($this->document)) {
            $errors[] = new ValidationError(
                field: 'document',
                rule: 'file_valid',
                message: 'Документ повреждён или формат не поддерживается',
                input: $this->document->filename,
            );
        }
        return $errors;
    }
}
```

Порядок в пайплайне: attribute-валидация → `validateCustom()` → RequestContractValidator → сериализация.
Ошибки данных объединяются и возвращаются через штатный `buildValidationFailure` (без HTTP-вызова провайдера).
Если объявленные attribute-проверки недоступны, ошибка конфигурации останавливает
выполнение до custom preflight, composite, сериализации и HTTP.

Для проверки файлов по пути используйте `FileInput::tryFromPath()` — небросающий вариант;
при `null` добавляйте `ValidationError` вместо раннего `ConfigurationException`.

## Валидация DTO
```php
$dto = UserDto::from($data)->validate();   // бросит ValidationException
$ok = $dto->isValid();                     // bool
$errors = $dto->errors();                  // array<ValidationError>
```

## Миграция и различие ошибок

| Результат | Поведение |
| --- | --- |
| Данные не соответствуют правилам работающего валидатора | `validation_failed`, список `ValidationError`, без HTTP; ручной `isValid()` возвращает false. |
| Объявлены правила, но нет совместимой фабрики / не удалось её получить | `configuration_error`, без HTTP и без ошибок полей; прямые `validate()`/`isValid()`/`errors()` выбрасывают `ConfigurationException`. |
| Нет `#[Validate]` | Фабрика не требуется; custom preflight и контракты запроса продолжают работать. |

При обновлении учтите два изменения: молчаливый пропуск правил больше не выполняется,
а явный provider клиента имеет приоритет над глобальной фабрикой. Существующие
однопараметровые вызовы Validator сохраняются. Для pipeline `raw()`/`resolved()`
возвращают ошибку, `dataOrFail()`/`throwOnErrors` выбрасывают соответствующее исключение;
sync и promise API используют один контракт.

## Кастомные сообщения
Два уровня:
1) `message` в `#[Validate]`
2) `static validationMessages(): array` в классе (если нужно много правил)

Используйте `message`, когда у свойства 1‑2 простых правила.
`validationMessages()` удобен, если нужно покрыть несколько полей/правил
в одном месте.

Пример:
```php
public static function validationMessages(): array
{
    return [
        'email.required' => 'Email обязателен',
        'email.email' => 'Некорректный email',
    ];
}
```
