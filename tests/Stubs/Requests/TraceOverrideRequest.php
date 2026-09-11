<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;

#[Get('/trace-override')]
final class TraceOverrideRequest extends AbstractRequest
{
    public function __construct(
        private string $traceId,
        private ?RequestRole $role = null,
    ) {}

    #[\Override]
    public function getTraceIdOverride(): ?string
    {
        return $this->traceId;
    }

    #[\Override]
    public function getRoleOverride(): ?RequestRole
    {
        return $this->role;
    }
}
