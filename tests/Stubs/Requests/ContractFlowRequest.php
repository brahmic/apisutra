<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContractFlowDto;
use Override;

#[Post('/items/{id}')]
#[Returns(ContractFlowDto::class)]
final class ContractFlowRequest extends AbstractRequest
{
    public function __construct(
        private HttpMethod $method,
        #[Path] public string $id = 'a/b',
        #[Query] public int $offset = 0,
        #[Header('X-Fixture')] public string $header = 'fixture',
        #[Body] public bool $enabled = false,
    ) {
    }

    #[Override]
    public function getMethod(): HttpMethod
    {
        return $this->method;
    }
}
