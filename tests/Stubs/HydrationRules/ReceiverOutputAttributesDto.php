<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;

final readonly class ReceiverOutputAttributesDto
{
    public function __construct(
        #[To('out')] public array $to = [],
        #[DateTimeTo] public array $date = [],
        #[Query] public array $query = [],
        #[Body] public array $body = [],
        #[BodyRoot] public array $root = [],
        #[Header('X-Value')] public array $header = [],
        #[Path('value')] public array $path = [],
        #[File] public array $file = [],
    ) {
    }
}
