# Валидация

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

**В Laravel-проекте** — работает автоматически. `LaravelContainerProvider` предоставляет `validatorFactory()` из контейнера.

**Standalone** — SDK получает factory двумя способами:
- `Validator::useFactory($factory)` — явная установка
- `ContainerProviderInterface::validatorFactory()` — через кастомный провайдер

Если factory не задан — валидация `#[Validate]` **пропускается** (считается valid).
`CustomValidatableRequestInterface` работает независимо от factory.

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
Ошибки объединяются и возвращаются через штатный `buildValidationFailure` (без provider call, без ConfigurationException).

Для проверки файлов по пути используйте `FileInput::tryFromPath()` — небросающий вариант;
при `null` добавляйте `ValidationError` вместо раннего `ConfigurationException`.

## Валидация DTO
```php
$dto = UserDto::from($data)->validate();   // бросит ValidationException
$ok = $dto->isValid();                     // bool
$errors = $dto->errors();                  // array<ValidationError>
```

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

## Где детали
- Атрибуты: `docs/guides/attributes/data-transfer.md`
