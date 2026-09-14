<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class DtoRules
{
    /** @param array<string, FieldRule> $fields */
    private function __construct(
        public RulePolicy $policy,
        public array $fields = [],
        public ?string $receiver = null,
    ) {
    }

    public static function create(?RulePolicy $policy = null): self
    {
        return new self($policy ?? new RulePolicy());
    }

    public function field(string $property, FieldRule $rule): self
    {
        if (array_key_exists($property, $this->fields)) {
            throw new ConfigurationException('Правило поля уже задано: ' . $property);
        }
        return new self($this->policy, $this->fields + [$property => $rule], $this->receiver);
    }

    public function extras(string $property): self
    {
        if ($this->receiver !== null) {
            throw new ConfigurationException('Receiver DTO уже задан');
        }
        return new self($this->policy, $this->fields, $property);
    }
}
