<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Patch('/body-root/query-defaults')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Query)]
final class BodyRootWithQueryDefaultsRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[BodyRoot]
        public array $operations,
        public string $plain,
    ) {}
}
