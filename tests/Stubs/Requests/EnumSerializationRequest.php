<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[Post('/enum/{status}')]
final class EnumSerializationRequest extends AbstractRequest
{
    /**
     * @param array<int, TitleStatus> $queryStatuses
     * @param array<int, TitleStatus> $bodyStatuses
     */
    public function __construct(
        #[Path('status')]
        public TitleStatus $pathStatus,
        #[Query('status_query')]
        public TitleStatus $queryStatus,
        #[Query('status_list')]
        public array $queryStatuses,
        #[Header('X-Status')]
        public TitleStatus $headerStatus,
        #[Body]
        public TitleStatus $bodyStatus,
        #[Body]
        public array $bodyStatuses,
    ) {}
}
