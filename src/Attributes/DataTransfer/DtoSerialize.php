<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\DataTransfer;

use Attribute;
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoSerialize
{
    public function __construct(
        public ?EnumOutput $enumOutput = null,
        public ?bool $strictEnums = null,
        public ?NamingStrategy $namingStrategy = null,
        public ?bool $serializeNulls = null,
        public ?string $dateTimeFormat = null,
        public ?string $dateTimeTimezone = null,
    ) {}

    public function toPolicy(DtoSerializationPolicy $base): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: $this->enumOutput ?? $base->enumOutput,
            strictEnums: $this->strictEnums ?? $base->strictEnums,
            namingStrategy: $this->namingStrategy ?? $base->namingStrategy,
            serializeNulls: $this->serializeNulls ?? $base->serializeNulls,
            dateTime: new DateTimeSerializationPolicy(
                format: $this->dateTimeFormat ?? $base->dateTime->format,
                timezone: $this->dateTimeTimezone ?? $base->dateTime->timezone,
            ),
        );
    }
}
