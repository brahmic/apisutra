<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use ReflectionClass;

/** @internal Описание класса для одного набора; не содержит состояния гидратации. */
final readonly class CompiledDtoRules
{
    public function __construct(
        public ReflectionClass $reflection,
        public ?DtoRules $declaration,
        public RulePolicy $policy,
        public bool $legacyProfile,
    ) {
    }

    public function field(string $property): ?FieldRule
    {
        return $this->declaration?->fields[$property] ?? null;
    }

    public function policyFor(string $property): RulePolicy
    {
        return $this->field($property)?->policy?->over($this->policy) ?? $this->policy;
    }
}
