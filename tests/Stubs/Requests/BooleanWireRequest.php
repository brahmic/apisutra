<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Casts\BooleanCast;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;

#[Post('/booleans')]
class BooleanWireRequest extends AbstractRequest
{
    #[Query]
    public bool $default = false;

    #[Query]
    #[Cast(BooleanCast::class, BooleanFormat::Literal)]
    public bool $literal = false;

    #[Query]
    #[Cast(BooleanCast::class, BooleanFormat::Numeric)]
    public bool $numeric = true;

    /** @var array{nested: array{enabled: bool}} */
    #[Query]
    #[Cast(JsonCast::class)]
    public array $structure = ['nested' => ['enabled' => false]];

    #[Body]
    public bool $bodyBoolean = false;

    /** @var list<bool> */
    #[Body]
    public array $bodyArray = [false, true];

    #[Body]
    #[Cast(BooleanCast::class, BooleanFormat::Literal)]
    public bool $bodyLiteral = false;
}
