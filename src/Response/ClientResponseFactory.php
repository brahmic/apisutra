<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Response;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;
use Brahmic\ApiSutra\VO\Errors\ClientErrorMapperAwareInterface;
use Brahmic\ApiSutra\VO\Errors\ClientErrorMapperInterface;
use Brahmic\ApiSutra\VO\Errors\DefaultClientErrorMapper;

/**
 * Дефолтная фабрика клиентского ответа.
 */
final readonly class ClientResponseFactory implements ClientResponseFactoryInterface, ClientErrorMapperAwareInterface
{
    public const string KEY_DATA = 'data';
    public const string KEY_ERRORS = 'errors';

    private ClientErrorMapperInterface $mapper;
    private ClientErrorFactory $errorFactory;

    public function __construct(
        ?ClientErrorMapperInterface $mapper = null,
    ) {
        $this->mapper = $mapper ?? new DefaultClientErrorMapper();
        $this->errorFactory = new ClientErrorFactory($this->mapper);
    }

    public function withErrorMapper(ClientErrorMapperInterface $mapper): static
    {
        return new self($mapper);
    }

    public function make(ResolvedResultInterface $result): ClientResponse
    {
        $execution = $result->result();
        $errors = $this->mapErrors($execution->errors);

        return match ($execution->status) {
            ResultStatus::SUCCESS => new ClientResponse(
                status: 200,
                body: $execution->data,
            ),
            ResultStatus::PARTIAL => new ClientResponse(
                status: 207,
                body: [
                    self::KEY_DATA => $execution->data,
                    self::KEY_ERRORS => $errors,
                ],
            ),
            ResultStatus::FAILED => new ClientResponse(
                status: $this->mapper->status($execution->errors),
                body: [
                    self::KEY_ERRORS => $errors,
                ],
            ),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapErrors(ErrorCollection $errors): array
    {
        $mapped = $this->errorFactory->makeMany($errors);

        return array_map(
            static fn (ClientError $error): array => $error->toArray(),
            $mapped,
        );
    }
}
