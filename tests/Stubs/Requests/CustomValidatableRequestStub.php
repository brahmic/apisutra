<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\VO\Errors\ValidationError;

#[Get('/custom-validatable')]
#[Returns(SimpleResponseDto::class)]
final class CustomValidatableRequestStub extends AbstractRequest implements CustomValidatableRequestInterface
{
    /** @var array<ValidationError> Ошибки для validateCustom() (тестовый stub). */
    public array $customValidationErrors = [];

    public function __construct(
        #[Query]
        public string $query = '',
    ) {}

    #[\Override]
    public function validateCustom(): array
    {
        return $this->customValidationErrors;
    }
}
