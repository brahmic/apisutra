<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\DataTransfer;

use Attribute;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Cast
{
    public array $args;

    /**
     * @param class-string<CastInterface> $class Класс каста.
     * @param mixed ...$args Аргументы конструктора каста.
     */
    public function __construct(
        public string $class,
        mixed ...$args,
    ) {
        $this->args = $args;
    }
}
