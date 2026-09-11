<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;

#[Post('/serialize/{id}')]
final class SerializationRequest extends AbstractRequest
{
    /**
     * @param array<int, string> $filters
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        #[Query('filters', arrayFormat: QueryArrayFormat::Comma)]
        public array $filters,
        #[Body(nested: 'payload.data')]
        public string $payload,
        #[Header('X-Custom')]
        public string $header,
        public string $plainValue,
    ) {}
}
