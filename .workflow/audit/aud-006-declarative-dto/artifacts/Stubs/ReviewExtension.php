<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Extensions\ExtensionContext;

final readonly class ReviewExtension implements ExtensionInterface
{
    public function __construct(private ?ReviewResponseHandler $handler = null)
    {
    }

    public function getName(): string
    {
        return 'dto-audit-review';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerCast('int', new StrictScalarCast('int'));
        if ($this->handler !== null) {
            $context->registerResponseHandler('application/json', $this->handler);
        }
    }

    public function boot(ClientConfig $config): void
    {
    }

    public function checkDependencies(): void
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
