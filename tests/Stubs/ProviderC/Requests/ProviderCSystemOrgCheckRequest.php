<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;

#[Get('/org-check.json')]
#[Returns(ProviderCSystemResponseDto::class)]
final class ProviderCSystemOrgCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'inn')]
        public ?string $inn = null,
        #[Query(name: 'ogrn')]
        public ?string $ogrn = null,
    ) {}
}
