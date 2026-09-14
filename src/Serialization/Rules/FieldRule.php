<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class FieldRule
{
    /** @param list<string> $fallback */
    private function __construct(
        public ?string $from = null,
        public array $fallback = [],
        public ?ValueShape $shape = null,
        public ?HandlerSpec $cast = null,
        public bool $noTransform = false,
        public bool $required = false,
        public bool $forbidExplicitNull = false,
        public ?InputShape $inputShape = null,
        public ?DefaultSpec $default = null,
        public ?RulePolicy $policy = null,
        private bool $transformationSet = false,
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public function from(string $path, string ...$fallback): self
    {
        $this->assertUnset($this->from !== null, 'from');
        return $this->copy(['from' => $path, 'fallback' => $fallback]);
    }

    public function shape(ValueShape $shape): self
    {
        $this->assertUnset($this->transformationSet, 'преобразование');
        return $this->copy(['shape' => $shape, 'transformationSet' => true]);
    }

    public function cast(HandlerSpec $cast, ?ValueShape $result = null): self
    {
        $this->assertUnset($this->transformationSet, 'преобразование');
        return $this->copy(['cast' => $cast, 'shape' => $result, 'transformationSet' => true]);
    }

    public function noTransform(): self
    {
        $this->assertUnset($this->transformationSet, 'преобразование');
        return $this->copy(['noTransform' => true, 'transformationSet' => true]);
    }

    public function required(): self
    {
        return $this->copy(['required' => true]);
    }

    public function forbidExplicitNull(): self
    {
        return $this->copy(['forbidExplicitNull' => true]);
    }

    public function inputShape(InputShape $shape): self
    {
        $this->assertUnset($this->inputShape !== null, 'inputShape');
        return $this->copy(['inputShape' => $shape]);
    }

    public function default(DefaultSpec $default): self
    {
        $this->assertUnset($this->default !== null, 'default');
        return $this->copy(['default' => $default]);
    }

    public function policy(RulePolicy $policy): self
    {
        $this->assertUnset($this->policy !== null, 'policy');
        if ($policy->casts !== []) {
            throw new ConfigurationException('Cast поля задаётся через FieldRule.cast, не RulePolicy.casts');
        }
        return $this->copy(['policy' => $policy]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_replace(get_object_vars($this), $changes));
    }

    private function assertUnset(bool $set, string $group): void
    {
        if ($set) {
            throw new ConfigurationException('Группа FieldRule уже задана: ' . $group);
        }
    }
}
