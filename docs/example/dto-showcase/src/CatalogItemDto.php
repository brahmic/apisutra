<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerialize;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use Brahmic\ApiSutra\VO\Files\Base64File;
use DateTimeImmutable;

// toArray() сохраняет null и строковые значения enum.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class CatalogItemDto extends AbstractDto
{
    /**
     * @param list<int> $relatedIds
     * @param list<ImageDto|VideoDto> $media
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Входное имя с запасным путём; исходящее имя задаётся отдельно.
        #[From('product_id', fallback: ['id'])]
        #[To('product_id')]
        public int $id,
        // Одно внешнее имя для чтения и записи.
        #[Map('vendor_code')]
        public string $sku,
        // Отсутствие и явный null разрешены контрактом вымышленного API.
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // Ключ обязателен, но его значение может быть null или пустой строкой.
        #[EmptyStringAsNull(blank: true)]
        public ?string $description,
        // Набор правил ниже проверяет точный тип bool.
        public bool $available,
        // Вложенный путь можно развернуть в отдельное свойство DTO.
        #[From('metrics.rating')]
        public float $rating,
        // Вход содержит время с часовым поясом, исходящий формат — календарную дату.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: 'Y-m-d', timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // Строковое значение API превращается в backed enum.
        #[From('state')]
        #[To('state')]
        public ItemStatus $status,
        // Собственный cast читает "12.34" как 1234 и выполняет обратное преобразование.
        #[From('price')]
        #[To('price')]
        #[Cast(MinorUnitsCast::class)]
        public int $priceMinor,
        // Файл внутри JSON: вход допускает data URI, выход содержит чистый Base64.
        #[Map('manual_file')]
        #[Cast(DataUriBase64FileCast::class)]
        public Base64File $manual,
        // SellerDto — обычный PHP-класс; Nested создаёт отдельный вложенный объект.
        #[Nested(type: SellerDto::class)]
        public SellerDto $seller,
        // Элементы становятся DTO, контейнер проверяет их тип и даёт first()/count().
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Элементы массива проверяет ValueShape::list(), а не PHPDoc.
        #[To('related_ids')]
        public array $relatedIds,
        // Типы вариантов и извлечение value объявлены во внешнем наборе.
        #[To('assets')]
        public array $media,
        // Provider вычисляет отсутствующее значение по исходным данным DTO.
        #[DefaultValue(provider: DisplayNameProvider::class)]
        public string $displayName,
        // Отсутствие разрешено; явный null запрещает внешнее правило.
        public ?int $stock = null,
        // Непрочитанные поля; сбор включается через extras('_extra').
        public array $_extra = [],
    ) {
    }
}
