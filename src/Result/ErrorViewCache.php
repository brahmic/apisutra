<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;

/**
 * Простой кеш для маппинга ошибок.
 */
final class ErrorViewCache
{
    /**
     * @var array<int, ClientError>|null
     */
    private ?array $views = null;

    /**
     * @return array<int, ClientError>
     */
    public function get(ErrorCollection $errors, ClientErrorFactory $factory): array
    {
        if ($this->views === null) {
            $this->views = $factory->makeMany($errors);
        }

        return $this->views;
    }
}
