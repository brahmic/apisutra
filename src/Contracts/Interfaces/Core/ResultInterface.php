<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

interface ResultInterface
{
    public function isSuccess(): bool;
    public function isFailed(): bool;
    public function hasData(): bool;
    public function hasErrors(): bool;
}
