<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use DateTimeImmutable;

#[Post('/union-date-right')]
final class UnionDateTimeRightRequest extends AbstractRequest
{
    public function __construct(
        #[Query('created_at')]
        public string|DateTimeImmutable $createdAt,
        #[Body('payload.created_at')]
        public string|DateTimeImmutable $payloadCreatedAt,
    ) {}
}
