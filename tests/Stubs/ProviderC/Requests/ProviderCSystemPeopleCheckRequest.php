<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;

#[Get('/people-check.json')]
#[Returns(ProviderCSystemResponseDto::class)]
final class ProviderCSystemPeopleCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'PeopleQuery.LastName')]
        public string $lastName,
        #[Query(name: 'PeopleQuery.FirstName')]
        public string $firstName,
        #[Query(name: 'regions')]
        public string $regions,
        #[Query(name: 'PeopleQuery.BirthDate')]
        public ?string $birthDate = null,
    ) {}
}
