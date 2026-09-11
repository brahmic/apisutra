<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Pagination;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Pagination\AbstractPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCPaginationResultDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCRoleDataItemDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportEvent;


#[Get('/report/{uuid}/org-judge.json')]
#[Returns(response: ProviderCPaginationResultDto::class)]
#[Pagination(itemsType: ProviderCRoleDataItemDto::class)]
final class ProviderCReportJudgeRoleDataRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'event')]
        public ProviderCReportEvent $event = ProviderCReportEvent::RoleData,
        #[Query(name: 'filter0')]
        public ?string $filter = null,
        #[Query(name: 'filter_text')]
        public ?string $filterText = null,
        #[Query(name: 'strategy')]
        public ?string $strategy = null,
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $rows = null,
    ) {}

}
