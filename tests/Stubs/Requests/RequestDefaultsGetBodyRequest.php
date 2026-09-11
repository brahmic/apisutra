<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Ignore;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Get('/defaults/{id}')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class RequestDefaultsGetBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Path('id')]
        public string $id,
        public string $plain,
        #[Query('q')]
        public string $query,
        #[Body('payload.explicit')]
        public string $explicitBody,
        #[Header('X-Mode')]
        public string $mode = 'test',
        #[Ignore]
        #[Body]
        public ?string $ignoredBody = null,
    ) {}
}
