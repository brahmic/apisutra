<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Override;

#[Get('/retry-policy')]
class RetryPolicyRequest extends AbstractRequest
{
    public function __construct(private HttpMethod $method = HttpMethod::GET) {}

    #[Override]
    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    #[Override]
    public function getEndpoint(): string
    {
        return '/retry-policy';
    }
}
