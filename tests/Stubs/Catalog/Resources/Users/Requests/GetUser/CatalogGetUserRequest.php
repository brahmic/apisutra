<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto\CatalogUserDto;

#[Get('/catalog/users')]
#[Returns(CatalogUserDto::class)]
#[OperationDescriptor(title: 'Получить пользователя')]
final class CatalogGetUserRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
