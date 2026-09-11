<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Files\Base64File;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class DataUriBase64FileCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?Base64File
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new ConfigurationException('DataUriBase64FileCast::hydrate ожидает string|null');
        }

        $normalized = $this->normalizeBase64String($value);
        if ($normalized === null) {
            return null;
        }

        $this->assertValidBase64($normalized);

        return new Base64File($normalized);
    }

    #[\Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Base64File) {
            return base64_encode($value->content());
        }

        if (!is_string($value)) {
            throw new ConfigurationException('DataUriBase64FileCast::serialize ожидает Base64File|string|null');
        }

        $normalized = $this->normalizeBase64String($value);
        if ($normalized === null) {
            return null;
        }

        $this->assertValidBase64($normalized);

        return $normalized;
    }

    private function normalizeBase64String(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^data:[^,]*;base64,\s*(.+)$/is', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertValidBase64(string $value): void
    {
        if (base64_decode($value, true) === false) {
            throw new ConfigurationException('DataUriBase64FileCast получил невалидный base64 payload');
        }
    }
}
