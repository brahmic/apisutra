<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/hydration-contract')]
final class ContractRequest extends AbstractRequest
{
    /** @param class-string $dtoType */
    public function __construct(
        private readonly string $dtoType,
        private readonly ?string $unwrap = null,
        #[Body] public readonly ?string $value = null,
    ) {
    }

    public function getResponseType(): ?string
    {
        return $this->dtoType;
    }

    public function getReturnsAttribute(): ?Returns
    {
        return new Returns($this->dtoType, unwrap: $this->unwrap);
    }
}
