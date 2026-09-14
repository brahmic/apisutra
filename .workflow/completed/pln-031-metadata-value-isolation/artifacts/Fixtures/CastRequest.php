<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031\Fixtures;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/cast')]
#[Returns(CastDto::class)]
final class CastRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        #[Cast(CountingCast::class, new MutableCounter())]
        public int $number = 0,
    ) {
    }
}
