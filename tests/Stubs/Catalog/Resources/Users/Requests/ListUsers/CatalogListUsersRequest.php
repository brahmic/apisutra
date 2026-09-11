<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\ListUsers;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto\CatalogUserDto;

#[Get('/catalog/users/list')]
#[Returns(CatalogUserDto::class)]
final class CatalogListUsersRequest extends AbstractRequest
{
}
