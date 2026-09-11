<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\RequestOneOf;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/duplicate')]
#[RequestOneOf(
    name: 'dup',
    variants: [
        'alpha' => ['alphaData'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestOneOf(
    name: 'dup',
    variants: [
        'beta' => ['betaData'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
final class OneOfDuplicateContractsRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $alphaData = null,
        #[Body]
        public ?string $betaData = null,
    ) {}
}
