<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/continuation/mode')]
final class ContinuationModeRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public bool $manual_async = false,
    ) {}
}
