<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Auth\ProviderCAuthPolicy;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCReportResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCRoleDataItemDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCRoleHistoryItemDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportEvent;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Pagination\ProviderCPaginationMetaResolver;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\ProviderCStatusMap;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportDownloadRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJsonRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJudgePreviewRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJudgeRoleDataRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCReportJudgeRoleHistoryRequest;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemPeopleCheckRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\TestClientFactory;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Files\FileResponse;

describe('Provider C emulation', function () {
    beforeEach(function () {
        $this->providerCConfig = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: new RecordingAuthenticator(),
            authPolicy: new ProviderCAuthPolicy(),
            paginationConfig: new PaginationConfig(
                pageParam: 'page',
                limitParam: 'rows',
                metaPath: 'response',
                itemsPath: 'response.result',
                metaResolver: ProviderCPaginationMetaResolver::class,
                itemsCollection: TestItemCollection::class,
            ),
            environment: Environment::Testing,
        );

        $this->providerCOverrides = [
            'auth' => new RecordingAuthenticator(),
            'authPolicy' => new ProviderCAuthPolicy(),
            'paginationConfig' => new PaginationConfig(
                pageParam: 'page',
                limitParam: 'rows',
                metaPath: 'response',
                itemsPath: 'response.result',
                metaResolver: ProviderCPaginationMetaResolver::class,
                itemsCollection: TestItemCollection::class,
            ),
            'environment' => Environment::Testing,
        ];
    });
    it('гидрирует системный ответ в DTO', function () {
        $dto = ProviderCSystemResponseDto::from([
            'status' => 0,
            'query_type' => 1,
            'uuid' => 'uuid-1',
        ]);

        expect($dto->status)->toBe(ProviderCSystemStatus::Ok);
        expect($dto->queryType)->toBe(1);
        expect($dto->uuid)->toBe('uuid-1');
    });

    it('гидрирует асинхронный отчёт в DTO', function () {
        $dto = ProviderCReportResponseDto::from([
            'status' => 0,
            'waitTime' => 1500,
            'response' => ['count' => 1],
        ]);

        expect($dto->status)->toBe(ProviderCReportStatus::Waiting);
        expect($dto->waitTime)->toBe(1500);
        expect($dto->response)->toBe(['count' => 1]);
    });

    it('маппит статусы системы и отчёта', function () {
        expect(ProviderCStatusMap::mapSystem(ProviderCSystemStatus::Ok))
            ->toBe(ResultStatus::SUCCESS);
        expect(ProviderCStatusMap::mapSystem(ProviderCSystemStatus::TariffExpired))
            ->toBe(ResultStatus::FAILED);

        expect(ProviderCStatusMap::mapReport(ProviderCReportStatus::Ready, null))
            ->toBe(ResultStatus::SUCCESS);
        expect(ProviderCStatusMap::mapReport(ProviderCReportStatus::Waiting, 1000))
            ->toBe(ResultStatus::PARTIAL);
    });

    it('подставляет параметры с точками', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCSystemPeopleCheckRequest::class => MockResponse::success([
                'status' => 0,
                'query_type' => 1,
                'uuid' => 'uuid-1',
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);
        $request = new ProviderCSystemPeopleCheckRequest(
            token: 'token-1',
            lastName: 'Ivanov',
            firstName: 'Ivan',
            regions: '[77]',
            birthDate: '01.01.1990',
        );
        $request->setClient($client);

        $request->send()->raw();

        $recorded = $transport->getRecorded()[0] ?? null;
        $query = $recorded?->meta['query'] ?? [];

        expect($query['token']['value'] ?? null)->toBe('token-1');
        expect($query['PeopleQuery.LastName']['value'] ?? null)->toBe('Ivanov');
        expect($query['PeopleQuery.FirstName']['value'] ?? null)->toBe('Ivan');
        expect($query['PeopleQuery.BirthDate']['value'] ?? null)->toBe('01.01.1990');
        expect($query['regions']['value'] ?? null)->toBe('[77]');
    });

    it('поддерживает wait → ready для асинхронного отчёта', function () {
        $client = TestClientFactory::make(
            [
                ProviderCReportJudgePreviewRequest::class => MockResponse::sequence([
                    MockResponse::success(['status' => 0, 'waitTime' => 1000]),
                    MockResponse::success([
                        'status' => 1,
                        'response' => ['count' => 1],
                    ]),
                ]),
            ],
            $this->providerCOverrides,
        );

        $request = new ProviderCReportJudgePreviewRequest(
            uuid: 'uuid-10',
            token: 'token-10',
            event: ProviderCReportEvent::RolePreview,
        );
        $request->setClient($client);

        $first = $request->withoutCache()->send()->raw()->data;
        $second = $request->withoutCache()->send()->raw()->data;

        expect($first->status)->toBe(ProviderCReportStatus::Waiting);
        expect($second->status)->toBe(ProviderCReportStatus::Ready);
    });

    it('пагинирует отчёт role-data и учитывает page/rows', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCReportJudgeRoleDataRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 3,
                        'page' => 1,
                        'rows' => 2,
                        'result' => [
                            ['id' => 'r-1'],
                            ['id' => 'r-2'],
                        ],
                    ],
                ]),
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 3,
                        'page' => 2,
                        'rows' => 2,
                        'result' => [
                            ['id' => 'r-3'],
                        ],
                    ],
                ]),
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);
        $request = new ProviderCReportJudgeRoleDataRequest(
            uuid: 'uuid-20',
            token: 'token-20',
            event: ProviderCReportEvent::RoleData,
        );
        $request->setClient($client);

        $result = $request->paginate()->perPage(2)->all();

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(ProviderCRoleDataItemDto::class);
        expect($itemsArray)->toHaveCount(3);

        $recorded = $transport->getRecorded();
        $firstQuery = $recorded[0]->meta['query'] ?? [];
        $secondQuery = $recorded[1]->meta['query'] ?? [];

        expect($firstQuery['page']['value'] ?? null)->toBe(1);
        expect($firstQuery['rows']['value'] ?? null)->toBe(2);
        expect($firstQuery['event']['value'] ?? null)->toBe(ProviderCReportEvent::RoleData->value);
        expect($firstQuery['token']['value'] ?? null)->toBe('token-20');

        expect($secondQuery['page']['value'] ?? null)->toBe(2);
        expect($secondQuery['rows']['value'] ?? null)->toBe(2);
    });

    it('пагинирует role-history через общий meta-resolver', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCReportJudgeRoleHistoryRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 2,
                        'page' => 1,
                        'rows' => 1,
                        'result' => [
                            ['id' => 'h-1'],
                        ],
                    ],
                ]),
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 2,
                        'page' => 2,
                        'rows' => 1,
                        'result' => [
                            ['id' => 'h-2'],
                        ],
                    ],
                ]),
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);
        $request = new ProviderCReportJudgeRoleHistoryRequest(
            uuid: 'uuid-20',
            token: 'token-20',
        );
        $request->setClient($client);

        $result = $request->paginate()->perPage(1)->all();

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(ProviderCRoleHistoryItemDto::class);
        expect($itemsArray)->toHaveCount(2);

        $recorded = $transport->getRecorded();
        $firstQuery = $recorded[0]->meta['query'] ?? [];

        expect($firstQuery['event']['value'] ?? null)->toBe(ProviderCReportEvent::RoleHistory->value);
        expect($firstQuery['rows']['value'] ?? null)->toBe(1);
    });

    it('получает выбранную страницу через range', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCReportJudgeRoleDataRequest::class => MockResponse::success([
                'status' => 1,
                'response' => [
                    'count' => 3,
                    'page' => 2,
                    'rows' => 2,
                    'result' => [
                        ['id' => 'r-3'],
                    ],
                ],
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);
        $request = new ProviderCReportJudgeRoleDataRequest(
            uuid: 'uuid-21',
            token: 'token-21',
            event: ProviderCReportEvent::RoleData,
        );
        $request->setClient($client);

        $result = $request->paginate()->perPage(2)->range(2, 2);

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(ProviderCRoleDataItemDto::class);
        expect($itemsArray)->toHaveCount(1);

        $recorded = $transport->getRecorded();
        $query = $recorded[0]->meta['query'] ?? [];

        expect($query['page']['value'] ?? null)->toBe(2);
        expect($query['rows']['value'] ?? null)->toBe(2);
    });

    it('ограничивает количество страниц через pages()', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCReportJudgeRoleDataRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 4,
                        'page' => 1,
                        'rows' => 2,
                        'result' => [
                            ['id' => 'r-1'],
                            ['id' => 'r-2'],
                        ],
                    ],
                ]),
                MockResponse::success([
                    'status' => 1,
                    'response' => [
                        'count' => 4,
                        'page' => 2,
                        'rows' => 2,
                        'result' => [
                            ['id' => 'r-3'],
                            ['id' => 'r-4'],
                        ],
                    ],
                ]),
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);
        $request = new ProviderCReportJudgeRoleDataRequest(
            uuid: 'uuid-22',
            token: 'token-22',
            event: ProviderCReportEvent::RoleData,
        );
        $request->setClient($client);

        $result = $request->paginate()->perPage(2)->pages(1);

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(ProviderCRoleDataItemDto::class);
        expect($itemsArray)->toHaveCount(2);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('download и json отчёты корректно обрабатываются', function () {
        $transport = new MockTransport();
        $transport->fake([
            ProviderCReportDownloadRequest::class => MockResponse::make(
                'file-body',
                200,
                ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="report.pdf"'],
            ),
            ProviderCReportJsonRequest::class => MockResponse::success([
                'status' => 1,
                'response' => ['ok' => true],
            ]),
        ]);

        $config = $this->providerCConfig;
        $client = new TestClient($config, $transport);

        $download = new ProviderCReportDownloadRequest(
            uuid: 'uuid-30',
            format: 'pdf',
            token: 'token-30',
            reportName: 'org-print-form',
            timeout: 'PT2M',
        );
        $download->setClient($client);

        $downloadResult = $download->send()->raw();

        expect($downloadResult->data)->toBeInstanceOf(FileResponse::class);
        expect($downloadResult->data->content())->toBe('file-body');

        $json = new ProviderCReportJsonRequest(
            uuid: 'uuid-30',
            format: 'json',
            token: 'token-30',
            reportName: 'org-print-form',
            timeout: 'PT2M',
        );
        $json->setClient($client);

        $jsonResult = $json->send()->raw();

        expect($jsonResult->data)->toBe([
            'status' => 1,
            'response' => ['ok' => true],
        ]);

        $recorded = $transport->getRecorded();
        $downloadQuery = $recorded[0]->meta['query'] ?? [];

        expect($recorded[0]->url)->toContain('/report/uuid-30/report.pdf');
        expect($downloadQuery['report-name']['value'] ?? null)->toBe('org-print-form');
        expect($downloadQuery['token']['value'] ?? null)->toBe('token-30');
    });
});
