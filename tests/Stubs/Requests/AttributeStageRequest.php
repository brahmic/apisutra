<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\TagAttribute;

#[Get('/attribute-stage')]
#[TagAttribute('class')]
final class AttributeStageRequest extends AbstractRequest
{
    public function __construct(
        #[TagAttribute('property')]
        public string $payload = 'value',
    ) {}
}
