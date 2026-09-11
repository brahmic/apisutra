<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Ignore;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/conflict/ignore')]
final class BodyRootConflictIgnoreRequest extends AbstractRequest
{
    public function __construct(
        #[Ignore]
        #[BodyRoot]
        public string $payload,
    ) {}
}
