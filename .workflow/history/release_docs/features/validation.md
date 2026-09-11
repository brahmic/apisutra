# Валидация запросов

## Обзор

SDK валидирует свойства запроса перед отправкой. При ошибке — запрос не отправляется, возвращается результат с ошибками валидации.

**Синтаксис:** Laravel validation rules.

---

## Атрибут #[Validate]

```php
#[Post('/people-check')]
class GetPersonUuid extends AbstractRequest
{
    #[Validate('required|array|max:2')]
    public array $regions;
    
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
    
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $firstName;
    
    #[Validate('nullable|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public ?string $patronymic = null;
    
    #[Validate('nullable|date_format:d.m.Y')]
    public ?Carbon $birthDate = null;
    
    #[Validate('nullable|digits:4')]
    public ?string $passportSerial = null;
    
    #[Validate('nullable|digits:6')]
    public ?string $passportNumber = null;
    
    #[Validate('nullable|digits_between:10,12')]
    public ?string $inn = null;
}
```

---

## Механизм

1. SDK сканирует `#[Validate]` на свойствах запроса
2. Собирает массив правил: `['lastName' => 'required|regex:...', ...]`
3. Вызывает `Validator::make($data, $rules)`
4. При ошибке — не отправляет HTTP-запрос
5. Возвращает `ExecutionResult` с `ValidationError`

---

## ValidationError

Value Object ошибки валидации:

```php
readonly class ValidationError
{
    public function __construct(
        public string $field,      // 'lastName'
        public string $rule,       // 'regex'
        public string $message,    // 'Только кириллица'
        public mixed $input = null, // что было передано
    ) {}
}
```

### В ExecutionResult

```php
$result = $request->send();

if ($result->hasValidationErrors()) {
    foreach ($result->validationErrors as $error) {
        echo "{$error->field}: {$error->message}";
    }
    
    // Или конкретное поле
    $error = $result->validationErrorFor('lastName');
}
```

### Статус при ошибке валидации

```php
$result->status === ResultStatus::FAILED
$result->errors->first()->code === ErrorCode::ValidationFailed
$result->validationErrors // array<ValidationError> с деталями
```

---

## Кастомные сообщения

### Через параметр атрибута

```php
#[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/', message: 'Допускается только кириллица')]
public string $lastName;

#[Validate('required|array|max:2', message: 'Максимум 2 региона')]
public array $regions;
```

### Через метод класса

```php
class GetPersonUuid extends AbstractRequest
{
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
    
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $firstName;
    
    protected static function validationMessages(): array
    {
        return [
            'regex' => 'Поле :field содержит недопустимые символы',
            'required' => 'Поле :field обязательно для заполнения',
            'max' => 'Максимум :max значений',
        ];
    }
}
```

### Приоритет

```
Атрибут message > Метод validationMessages() > Дефолты Laravel
```

Атрибут более специфичен — переопределяет общие сообщения из метода.

---

## Атрибут #[Label]

Человекочитаемые имена полей в сообщениях об ошибках:

```php
class GetPersonUuid extends AbstractRequest
{
    #[Label('Фамилия')]
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
    
    #[Label('Имя')]
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $firstName;
    
    protected static function validationMessages(): array
    {
        return [
            'required' => 'Поле :field обязательно для заполнения',
            'regex' => 'Поле :field содержит недопустимые символы',
        ];
    }
}
```

**Результат:**
```
"Поле Фамилия обязательно для заполнения"
"Поле Имя содержит недопустимые символы"
```

Без `#[Label]` — используется имя свойства (`lastName`).

---

## Nullable = Optional

Свойства с `?` типом и `nullable` в правилах — необязательные:

```php
#[Validate('nullable|digits:4')]
public ?string $passportSerial = null;
```

- Если `null` — валидация проходит
- При `serializeNulls: false` — не отправляется в запросе

---

## Пример полного запроса

```php
#[Post('/people-check.json')]
#[Returns(UuidResponseDto::class)]
class GetPersonUuid extends AbstractRequest
{
    public const string NAME = 'Получение идентификатора проверки физического лица';
    
    #[Label('Регионы')]
    #[Validate('required|array|max:2')]
    public array $regions;
    
    #[Label('Фамилия')]
    #[Body(nested: 'PeopleQuery.LastName')]
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $lastName;
    
    #[Label('Имя')]
    #[Body(nested: 'PeopleQuery.FirstName')]
    #[Validate('required|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public string $firstName;
    
    #[Label('Отчество')]
    #[Body(nested: 'PeopleQuery.SecondName')]
    #[Validate('nullable|regex:/^[а-яёА-ЯЁ\- ]+/')]
    public ?string $patronymic = null;
    
    #[Label('Дата рождения')]
    #[Body(nested: 'PeopleQuery.BirthDate')]
    #[Validate('nullable|date_format:d.m.Y')]
    #[Cast(DateTimeCast::class, format: 'd.m.Y')]
    public ?Carbon $birthDate = null;
    
    #[Label('Серия паспорта')]
    #[Body(nested: 'PeopleQuery.PassportSeries')]
    #[Validate('nullable|digits:4')]
    public ?string $passportSerial = null;
    
    #[Label('Номер паспорта')]
    #[Body(nested: 'PeopleQuery.PassportNumber')]
    #[Validate('nullable|digits:6')]
    public ?string $passportNumber = null;
    
    #[Label('ИНН')]
    #[Body(nested: 'PeopleQuery.INN')]
    #[Validate('nullable|digits_between:10,12')]
    public ?string $inn = null;
    
    protected static function validationMessages(): array
    {
        return [
            'regex' => ':field должно содержать только кириллицу',
            'digits' => ':field должно содержать :digits цифр',
            'digits_between' => ':field должно содержать от :min до :max цифр',
            'max' => 'Максимум :max значений в поле :field',
        ];
    }
}
```

---

## Валидация DTO

DTO также поддерживает валидацию через те же атрибуты.

```php
readonly class UserInput extends AbstractDto
{
    public function __construct(
        #[Validate('required|email')]
        public string $email,
        
        #[Validate('required|min:2')]
        public string $name,
    ) {}
}
```

### Методы валидации

```php
$dto = UserInput::from($data);

// Вариант 1: Проверка с exception
$dto->validate();  // throws ValidationException

// Вариант 2: Проверка без exception
if (!$dto->isValid()) {
    $errors = $dto->errors();
    // обработка ошибок
}

// Вариант 3: Цепочка
UserInput::from($data)->validate()->send();
```

### ValidationException

```php
class ValidationException extends SdkException
{
    public function __construct(
        public readonly array $errors,
    ) {}
    
    public function errors(): array
    {
        return $this->errors;
    }
}
```

**Интеграция с Laravel:**

```php
// В Exception Handler
if ($e instanceof SdkValidationException) {
    throw \Illuminate\Validation\ValidationException::withMessages($e->errors());
}
```

---

## Резюме

| Элемент | Назначение |
|---------|------------|
| `#[Validate('rules')]` | Правила валидации (Laravel syntax) |
| `#[Label('Название')]` | Человекочитаемое имя для сообщений |
| `validationMessages()` | Кастомные сообщения для класса |
| `validate()` | Проверить с exception |
| `isValid()` | Проверить без exception |
| `errors()` | Получить ошибки |
| `ValidationException` | Exception при ошибках валидации |
| `ValidationError` | VO с деталями ошибки |
