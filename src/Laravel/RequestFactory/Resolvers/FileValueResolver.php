<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory\Resolvers;

use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolveContext;
use Brahmic\ApiSutra\Laravel\RequestFactory\ResolverInterface;
use Brahmic\ApiSutra\VO\Files\FileInput;

/**
 * Резолвер значений файлового ввода.
 */
final class FileValueResolver implements ResolverInterface
{
    /**
     * Проверяет, что для свойства задан файловый атрибут.
     */
    public function supports(ResolveContext $context): bool
    {
        return $context->fileAttribute !== null;
    }

    /**
     * Возвращает файл или массив файлов для свойства.
     */
    public function resolve(ResolveContext $context): FileInput|array|null
    {
        $fileName = $context->fileAttribute?->name ?? $context->propertyName;

        return $this->resolveFileValue($context->payload[PayloadKeys::FILES] ?? [], $fileName);
    }

    /**
     * Нормализует входной файл в FileInput или массив FileInput.
     *
     * @param array<string, mixed> $files Массив файлов из источника.
     */
    private function resolveFileValue(array $files, string $name): FileInput|array|null
    {
        $file = $files[$name] ?? null;
        if ($file instanceof FileInput) {
            return $file;
        }

        if (is_array($file)) {
            $converted = array_map(
                fn(mixed $item): ?FileInput => $this->convertUploadedFile($item),
                $file,
            );

            return array_values(array_filter(
                $converted,
                static fn(?FileInput $item): bool => $item instanceof FileInput,
            ));
        }

        return $this->convertUploadedFile($file);
    }

    /**
     * Преобразует загруженный файл в FileInput.
     */
    private function convertUploadedFile(mixed $file): ?FileInput
    {
        if ($file instanceof FileInput) {
            return $file;
        }

        if (!is_object($file)) {
            return null;
        }

        if (method_exists($file, 'getRealPath') && method_exists($file, 'getClientOriginalName')) {
            $path = $file->getRealPath();
            if ($path === false || $path === null) {
                return null;
            }

            $input = FileInput::fromPath($path);
            $mime = method_exists($file, 'getMimeType') ? $file->getMimeType() : null;
            if (is_string($mime)) {
                return $input->withMimeType($mime);
            }

            return $input;
        }

        return null;
    }
}
