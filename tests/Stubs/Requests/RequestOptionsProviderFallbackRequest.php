<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\RequestOptions;

#[Get('/provider-options')]
final class RequestOptionsProviderFallbackRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query = 'q',
    ) {}

    #[\Override]
    public function getOptions(): RequestOptions
    {
        return RequestOptions::empty()
            ->withBaseUrl('https://override.test')
            ->withTraceId('trace-from-provider-options');
    }
}
