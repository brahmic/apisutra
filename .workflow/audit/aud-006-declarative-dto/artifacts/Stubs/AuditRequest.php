<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/audit')]
final class AuditRequest extends AbstractRequest
{
    public function __construct(private readonly string $type, private readonly ?string $unwrap = null)
    {
    }

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->type;
    }

    #[Override]
    public function getReturnsAttribute(): ?Returns
    {
        return new Returns($this->type, $this->unwrap);
    }
}
