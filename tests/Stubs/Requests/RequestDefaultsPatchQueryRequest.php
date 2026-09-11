<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Ignore;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Patch('/defaults/{id}')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Query)]
final class RequestDefaultsPatchQueryRequest extends AbstractRequest
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        public string $plain,
        #[Body('payload.forced')]
        public string $forcedBody,
        #[Query('tags', arrayFormat: QueryArrayFormat::Comma)]
        public array $tags,
        #[Header('X-Mode')]
        public string $mode = 'test',
        #[File('document')]
        public ?FileInput $document = null,
        #[Ignore]
        public ?string $ignored = null,
    ) {}
}
