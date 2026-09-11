<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Testing;

use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use RuntimeException;

/**
 * Assertion-хелперы для live-тестов.
 *
 * Проверка успеха и типа данных в ResolvedResult.
 * Framework-agnostic: бросает RuntimeException при провале.
 */
final readonly class LiveResultAssertions
{
    /**
     * Бросает RuntimeException, если результат не успешен.
     */
    public static function assertSuccess(ResolvedResultInterface $resolved, string $operation): void
    {
        if (!$resolved->isSuccess()) {
            throw new RuntimeException(
                sprintf(
                    'Live-тест "%s" завершился ошибкой: status=%s, code=%s, message=%s',
                    $operation,
                    (string) ($resolved->errorStatus() ?? 'null'),
                    (string) ($resolved->errorCode() ?? 'null'),
                    (string) ($resolved->errorMessage() ?? 'null'),
                ),
            );
        }
    }

    /**
     * Проверяет успех и что data() — экземпляр ожидаемого класса.
     *
     * @template T of object
     * @param class-string<T> $expectedClass
     */
    public static function assertDataInstanceOf(
        ResolvedResultInterface $resolved,
        string $expectedClass,
        string $operation,
    ): void {
        self::assertSuccess($resolved, $operation);

        $data = $resolved->data();
        if ($data instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Live-тест "%s" вернул неожиданный тип данных: expected=%s, actual=%s',
                $operation,
                $expectedClass,
                get_debug_type($data),
            ),
        );
    }
}
