<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Attributes\Response\ContinuationResult;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioStartDto;
use Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask\CatalogPollTaskRequest;

#[Post('/catalog/reports/tasks/by-fio')]
#[Returns(CatalogCreateByFioStartDto::class, unwrap: 'data')]
#[ContinuationResult(
    finalType: CatalogCreateByFioFinalDto::class,
    pollRequest: CatalogPollTaskRequest::class,
    unwrap: 'result',
)]
#[OperationDescriptor(
    title: 'Создать отчёт по ФИО',
    description: 'Async-операция: возвращает task envelope, финальный DTO достаётся через poll.',
)]
final class CatalogCreateByFioRequest extends AbstractRequest
{
}
