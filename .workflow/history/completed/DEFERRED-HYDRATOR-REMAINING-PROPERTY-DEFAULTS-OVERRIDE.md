# Deferred: Hydrator remaining-property defaults / override semantics

## Статус
Отложено.

Возвращаться после текущей волны рефакторингов DTO/hydration/wire, отдельной задачей.

## Контекст
После внедрения модели:

- constructor-first
- property-fill fallback для remaining public properties

выявился дополнительный edge case, который текущая реализация покрывает не полностью.

## Проблема

Сейчас property-fill fallback хорошо работает для remaining properties, если они:

- не входят в constructor chain;
- ещё не инициализированы;
- должны получить значение из payload;
- либо nullable и должны стать `null` при missing.

Но возникает отдельный сценарий:

```php
class X extends BaseDto
{
    public array $result = [];
}
```

Если:
- свойство `result` не входит в constructor chain;
- у него есть inline default;
- в payload приходит `result`;

то текущее поведение такое:

1. свойство уже считается initialized;
2. fallback assignment видит initialized property;
3. срабатывает guard `already initialized before fallback assignment`;
4. значение из payload не может корректно затереть default.

## Важное уточнение
Для `readonly class` такой пример с `public array $result = [];` сам по себе невалиден на уровне PHP.

Но проблема шире `readonly`:

- она касается любого non-constructor property с inline default;
- особенно в non-readonly DTO shape;
- и в целом показывает, что current fallback contract пока ориентирован на "remaining but not initialized" properties.

## Текущий вывод
Сейчас supported path лучше считать таким:

- remaining property без inline default;
- nullable property, которую hydrator сам может инициализировать `null`;
- readonly remaining property без prior initialization.

А кейс:

- inline default
- + remaining property
- + payload override

требует отдельной доработки контракта.

---

## Что именно нужно решить

Нужно определить полное поведение hydrator-а для remaining properties в зависимости от комбинации:

1. свойство initialized / not initialized
2. readonly / non-readonly
3. payload value present / missing
4. nullable / non-nullable
5. inline default есть / нет

---

## Рекомендуемое целевое поведение

### Case A. Payload value отсутствует
- если есть inline default -> оставить default
- если default нет, но свойство nullable -> инициализировать `null`
- если default нет и свойство non-nullable -> явная ошибка

### Case B. Payload value присутствует
- если свойство не initialized -> присвоить payload value
- если свойство initialized:
  - для readonly -> ошибка
  - для non-readonly -> разрешить overwrite значением из payload

Это выглядит как наиболее логичная и полная модель.

---

## Почему задачу не добивали сейчас

1. Это уже следующий refinement поверх только что внедрённого hydration fallback.
2. Основной целевой кейс readonly/inheritance без конструктора уже закрыт.
3. Для завершения этой refinement-задачи нужно отдельно согласовать:
   - стоит ли разрешать overwrite initialized non-readonly properties;
   - насколько это совместимо с ожидаемым DTO contract пакета;
   - хотим ли мы официально поддерживать inline-default remaining props как first-class pattern.

---

## Что потребуется при возвращении к задаче

## 1. Обновить `Hydrator`
Пересмотреть `assignPropertyValues(...)` и split logic:

- distinguish initialized readonly vs initialized non-readonly
- allow controlled overwrite for non-readonly properties
- сохранить strictness для readonly

## 2. Добавить тесты

Минимум нужны кейсы:

- non-readonly remaining property с inline default + payload override
- non-readonly remaining property с inline default + missing payload
- readonly remaining property без default + payload value
- readonly remaining property nullable + missing
- non-nullable remaining property без default + missing

## 3. Уточнить документацию

Если поддержку inline-default remaining props утвердим, нужно обновить:

- `docs/guides/dto.md`
- `docs/glossary/dto.md`
- возможно `provider-methodology.md`

с чётким описанием, когда default сохраняется, а когда payload имеет приоритет.

---

## Риски

### 1. Скрытый mutation-semantics drift
Если разрешить overwrite initialized props слишком широко, можно размыть текущий hydration contract.

### 2. Неочевидное поведение для readonly / non-readonly
Нужно очень явно разделить эти две модели.

### 3. Разнобой между constructor-backed и property-backed DTO
Поведение должно оставаться предсказуемым и не зависеть от случайной формы DTO.

---

## Короткая формулировка задачи на будущее

Доработать hydrator так, чтобы remaining properties с inline defaults имели полностью определённое и удобное поведение:

- default сохраняется при `Missing`
- payload может переопределять default там, где это допустимо
- readonly invariants не нарушаются

