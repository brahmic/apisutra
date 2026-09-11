<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\RequestOptions;

final readonly class DelayApplier
{
    public function __construct(
        private ClientConfig $config,
    ) {}

    public function apply(RequestInterface $request, ?RequestOptions $options = null): void
    {
        $delay = $this->config->delay;
        if ($options?->getDelayOverride() !== null) {
            $delay = $options->getDelayOverride();
        } elseif ($request instanceof AbstractRequest && $request->getDelayOverride() !== null) {
            $delay = $request->getDelayOverride();
        }

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }
}
