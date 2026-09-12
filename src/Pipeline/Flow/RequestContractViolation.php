<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

final readonly class RequestContractViolation
{
    /**
     * @param array<int, string> $filledVariants
     * @param array<int, array<string, mixed>> $violations
     */
    public function __construct(
        public string $contract,
        public ?string $discriminatorField,
        public mixed $discriminatorValue,
        public ?string $matchedVariant,
        public array $filledVariants,
        public array $violations,
    ) {
    }

    public function message(): string
    {
        $first = $this->violations[0] ?? null;
        $details = is_array($first) && is_string($first['message'] ?? null)
            ? $first['message']
            : 'Нарушен контракт oneOf';

        return "Нарушен контракт oneOf '{$this->contract}': {$details}";
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'contract' => $this->contract,
            'discriminatorField' => $this->discriminatorField,
            'discriminatorValue' => $this->discriminatorValue,
            'matchedVariant' => $this->matchedVariant,
            'filledVariants' => $this->filledVariants,
            'violations' => $this->violations,
        ];
    }
}
