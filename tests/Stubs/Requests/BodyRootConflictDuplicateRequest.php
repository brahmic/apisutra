<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/conflict/duplicate')]
final class BodyRootConflictDuplicateRequest extends AbstractRequest
{
    public function __construct(
        #[BodyRoot]
        public string $first,
        #[BodyRoot]
        public string $second,
    ) {}
}
