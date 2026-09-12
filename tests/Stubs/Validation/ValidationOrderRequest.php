<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Validation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Label;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Override;

#[Post('/validation-order')]
final class ValidationOrderRequest extends AbstractRequest implements CompositeRequestInterface, CustomValidatableRequestInterface
{
    public int $customCalls = 0;
    public int $compositeCalls = 0;

    #[Validate('required', message: 'attribute :attribute')]
    #[Label('Email')]
    public string $email = '';

    #[Validate('required')]
    public string $name = '';

    #[Body]
    public string $payload = "\xB1";

    #[Override]
    public function validateCustom(): array
    {
        $this->customCalls++;
        return [new ValidationError('document', 'fixture', 'custom error', null)];
    }

    #[Override]
    public function requests(): RequestCollection
    {
        $this->compositeCalls++;
        return RequestCollection::make([]);
    }

    #[Override]
    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all();
    }

    /** @return array<string, string> */
    #[Override]
    protected static function validationMessages(): array
    {
        return ['email.required' => 'class email', 'name.required' => 'class name'];
    }
}
