<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

final readonly class SignatureParamsProvider implements AuthorizationParamsProviderInterface
{
    public function __construct(
        private string $secret,
        private int $timestamp,
    ) {}

    public function resolve(PreparedRequest $request): array
    {
        return [
            'ts' => $this->timestamp,
            'sign' => hash_hmac('sha256', $request->url . $this->timestamp, $this->secret),
        ];
    }
}
