<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/catalog/reports/tasks/poll')]
final class CatalogPollTaskRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $taskId,
    ) {}
}
