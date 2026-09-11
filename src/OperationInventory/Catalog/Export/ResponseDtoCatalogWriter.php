<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\OperationInventory\Catalog\Export;

use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogExporterInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

/**
 * Тонкий facade над набором exporter-ов для записи каталога в файл / рендера в строку.
 *
 * Принципы:
 * - сам ничего не форматирует
 * - выбирает exporter по format-id
 * - format может быть передан явно или выведен по расширению пути
 * - в случае конфликтов / отсутствия exporter-а — кидает ConfigurationException
 */
final readonly class ResponseDtoCatalogWriter
{
    /** @var array<string, ResponseDtoCatalogExporterInterface> */
    private array $exportersByFormat;

    /**
     * @param array<int, ResponseDtoCatalogExporterInterface> $exporters
     */
    public function __construct(array $exporters)
    {
        $this->exportersByFormat = $this->indexExporters($exporters);
    }

    public function writeTo(
        string $path,
        ResponseDtoCatalog $catalog,
        ?string $format = null,
    ): void {
        $resolvedFormat = $this->resolveFormat($path, $format);
        $content = $this->render($catalog, $resolvedFormat);

        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new ConfigurationException(sprintf('Не удалось создать директорию: %s', $directory));
            }
        }

        if (file_put_contents($path, $content) === false) {
            throw new ConfigurationException(sprintf('Не удалось записать каталог в файл: %s', $path));
        }
    }

    public function render(ResponseDtoCatalog $catalog, string $format): string
    {
        $normalized = $this->normalizeFormat($format);
        $exporter = $this->exportersByFormat[$normalized] ?? null;
        if ($exporter === null) {
            throw new ConfigurationException(sprintf(
                'Не найден exporter для формата "%s". Доступные: %s',
                $format,
                implode(', ', array_keys($this->exportersByFormat)),
            ));
        }

        return $exporter->export($catalog);
    }

    /**
     * @return array<int, string>
     */
    public function formats(): array
    {
        return array_keys($this->exportersByFormat);
    }

    private function resolveFormat(string $path, ?string $explicit): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $this->normalizeFormat($explicit);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!is_string($extension) || $extension === '') {
            throw new ConfigurationException(sprintf(
                'Не указан формат и не удалось определить его по расширению пути: %s',
                $path,
            ));
        }

        return $this->normalizeFormat($extension);
    }

    private function normalizeFormat(string $format): string
    {
        return strtolower(ltrim($format, '.'));
    }

    /**
     * @param  array<int, ResponseDtoCatalogExporterInterface>      $exporters
     * @return array<string, ResponseDtoCatalogExporterInterface>
     */
    private function indexExporters(array $exporters): array
    {
        $indexed = [];
        foreach ($exporters as $exporter) {
            $format = $this->normalizeFormat($exporter->format());
            if ($format === '') {
                throw new ConfigurationException(sprintf(
                    'Exporter "%s" вернул пустой format()',
                    $exporter::class,
                ));
            }
            if (isset($indexed[$format])) {
                throw new ConfigurationException(sprintf(
                    'Дублирующийся exporter для формата "%s": %s и %s',
                    $format,
                    $indexed[$format]::class,
                    $exporter::class,
                ));
            }
            $indexed[$format] = $exporter;
        }

        return $indexed;
    }
}
