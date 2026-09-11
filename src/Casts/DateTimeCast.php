<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Config\DateTimeHydrationPolicy;
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class DateTimeCast implements CastInterface
{
    private const string OFFSET_PATTERN = '/(Z|[+-]\d{2}(:?\d{2})?)$/';

    public function __construct(
        private readonly string $format = DATE_ATOM,
        private readonly ?string $timezone = null,
        private readonly ?DateTimeHydrationPolicy $hydratePolicy = null,
        private readonly ?DateTimeSerializationPolicy $serializePolicy = null,
    ) {}

    public static function fromHydrationPolicy(DateTimeHydrationPolicy $policy): self
    {
        return new self(hydratePolicy: $policy);
    }

    public static function fromSerializationPolicy(DateTimeSerializationPolicy $policy): self
    {
        return new self(serializePolicy: $policy);
    }

    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->hydratePolicy === null) {
            $timezone = $this->timezone ? new DateTimeZone($this->timezone) : null;
            $date = DateTimeImmutable::createFromFormat($this->format, (string) $value, $timezone ?: null);

            if ($date === false) {
                return new DateTimeImmutable((string) $value, $timezone ?: null);
            }

            return $date;
        }

        $stringValue = (string) $value;
        $parse = $this->hydratePolicy;
        $timezone = $parse->defaultTimezone ? new DateTimeZone($parse->defaultTimezone) : null;
        $hasOffset = $this->hasOffset($stringValue);

        $date = DateTimeImmutable::createFromFormat($parse->format, $stringValue, $timezone ?: null);
        if ($date === false) {
            if ($parse->strictFormat) {
                return $this->handleInvalid($parse, $stringValue);
            }

            try {
                $date = new DateTimeImmutable($stringValue, $timezone ?: null);
            } catch (Throwable $exception) {
                return $this->handleInvalid($parse, $stringValue, $exception);
            }
        }

        if (!$hasOffset && $parse->strictMissingTimezone) {
            return $this->handleInvalid($parse, $stringValue);
        }

        if ($hasOffset && !$parse->preserveOffset && $timezone !== null) {
            $date = $date->setTimezone($timezone);
        }

        return $date;
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw new ConfigurationException('DateTimeCast::serialize ожидает DateTimeInterface');
        }

        $date = $value;

        if ($this->serializePolicy === null) {
            return $date->format($this->format);
        }

        $format = $this->serializePolicy->format;
        $timezone = $this->serializePolicy->timezone;

        if ($timezone !== null) {
            $date = $date->setTimezone(new DateTimeZone($timezone));
        }

        return $date->format($format);
    }

    private function hasOffset(string $value): bool
    {
        return (bool) preg_match(self::OFFSET_PATTERN, $value);
    }

    private function handleInvalid(
        DateTimeHydrationPolicy $config,
        string $value,
        ?Throwable $exception = null,
    ): ?DateTimeInterface {
        if ($config->invalidBehavior === DateTimeInvalidBehavior::Null) {
            return null;
        }

        $message = 'Не удалось распарсить дату: ' . $value;
        if ($exception !== null) {
            $message .= '. ' . $exception->getMessage();
        }

        throw new ConfigurationException($message);
    }
}
