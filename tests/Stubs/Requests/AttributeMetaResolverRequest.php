<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Pagination\AttributeMetaResolver;

#[Get('/meta-attribute')]
#[Pagination(metaResolver: AttributeMetaResolver::class)]
final class AttributeMetaResolverRequest extends AbstractPaginatedRequest
{
}
