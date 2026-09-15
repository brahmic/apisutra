# Версии сервисов

Гайд по проектированию версий API‑сервисов в провайдере на базе ApiSutra.
Здесь описаны архитектурные правила, DX‑паттерны и границы кастомизации.

## Базовая идея
Версия относится к **сервису**, а не ко всему провайдеру.

Если у сервиса меняются глобальные настройки или контракт, версию нужно
оформлять как отдельную ветку (или отдельный сервис‑клиент).

## Когда нужна отдельная версия
Делайте version router, если меняется хотя бы одно:
- `baseUrl`
- `auth`
- `pagination`
- `serialization`
- DTO‑контракт endpoint

Если этого нет, обычно достаточно одного клиента + ресурсов.

## Рекомендуемый DX
- canonical: `->service()->v3()->resource()->method()`
- альтернатива: `->service()->useVersion(ServiceVersion::V3)->resource()->method()`

Рекомендация:
- бизнес‑код — canonical fluent‑стиль;
- инфраструктурный/динамический выбор версии — `useVersion(...)`.
- `serviceVersion(...)` используйте только как provider‑specific API, если он уже принят в проекте.

## Минимальный version router
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Core\AbstractResource;
use Brahmic\ApiSutra\Versioning\VersionedResourceTrait;

enum ServiceVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';
}

final class ServiceResource extends AbstractResource
{
    use VersionedResourceTrait;

    public function __construct(
        ClientInterface $client,
        private readonly string $version = 'v2',
    ) {
        parent::__construct($client);
    }

    public function v2(): self
    {
        return $this->v(ServiceVersion::V2);
    }

    public function v3(): self
    {
        return $this->v(ServiceVersion::V3);
    }

    public function useVersion(ServiceVersion $version): self
    {
        return $this->v($version);
    }

    public function resourceEntry(): AbstractResource
    {
        return $this->resourceByVersion([
            'v2' => V2\ResourceEntry::class,
            'v3' => V3\ResourceEntry::class,
        ]);
    }

    protected function currentVersionKey(): string
    {
        return $this->version;
    }

    protected function recreateWithVersion(string $version): static
    {
        return new self($this->client, $version);
    }
}
```

## Provider‑уровневая обёртка (рекомендуется)
Для крупных провайдеров удобно сделать свой базовый класс (например,
`AbstractVersionedResource`) поверх `VersionedResourceTrait` и вынести туда:
- хранение текущего version key;
- `currentVersionKey()` и `recreateWithVersion()`;
- дополнительные helper‑методы (например, `requestBySingleVersion(...)`).

## Что делает `VersionedResourceTrait`
- переключает версию через `v(UnitEnum $version)`
- роутит вложенные ресурсы через `resourceByVersion(...)`
- роутит запросы через `requestByVersion(...)`
- выбрасывает `UnsupportedVersionException` при неподдерживаемой версии

Трейт технический и опциональный: бизнес‑методы остаются в ресурсах.

## Политика обратной совместимости
- версия по умолчанию — часть публичного SDK‑контракта
- смена default версии — только через major‑релиз провайдера
- legacy‑методы держите в отдельной ветке (`v1()/legacy()`) или как
  временно `#[Deprecated]` на согласованный срок

## Граница `Shared/` и `V*`
В `Shared/` выносите только инвариантные классы:
- одинаковые поля/типы DTO
- одинаковая валидация и сериализация
- одинаковая семантика ошибок/пагинации
- без version‑specific веток внутри класса

Если условие нарушено — класс остаётся в `V*`.

## `compat-default` и strict explicit mode
Для переходного периода допустим режим `compat-default`, когда default‑ветка
маршрутизирует разные методы в разные версии (например, часть в `v2`, часть в `v3`).

Важно:
- в explicit‑режиме (`vX()` / `useVersion(...)`) fallback запрещён;
- неподдерживаемый маршрут должен завершаться `UnsupportedVersionException`.

## Тестовая матрица
Минимум для каждого versioned‑сервиса:
- контрактный happy‑path на `V2` и `V3`
- проверка роутера (`service()` = default, `service()->v3()` = новая версия)
- проверка explicit‑пути через `useVersion(...)`
- проверка `UnsupportedVersionException` для неподдерживаемой версии

## Антипаттерны
- скрытый env‑переключатель версии в production
- дублирование всего дерева `Resources` без выделения `Shared/`
- смешивание методов разных версий в одном ресурс‑классе без явного роутинга
