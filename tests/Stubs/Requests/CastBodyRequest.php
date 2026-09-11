<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

#[Post('/cast-body')]
final class CastBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        #[Cast(UppercaseCast::class)]
        public string $payload,
    ) {}
}
