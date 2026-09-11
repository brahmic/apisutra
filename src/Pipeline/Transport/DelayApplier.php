<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;

final readonly class DelayApplier
{
    public function __construct(
        private ClientConfig $config,
        private SleeperInterface $sleeper = new SystemSleeper(),
    ) {}

    public function apply(RequestInterface $request, ?RequestOptions $options = null, ?ExecutionBudget $budget = null): void
    {
        $delay = $this->config->delay;
        if ($options?->getDelayOverride() !== null) {
            $delay = $options->getDelayOverride();
        } elseif ($request instanceof AbstractRequest && $request->getDelayOverride() !== null) {
            $delay = $request->getDelayOverride();
        }

        if ($delay > 0) {
            ($budget ?? new ExecutionBudget(new SystemClock()))->wait($delay, $this->sleeper, 'request_delay');
        }
    }
}
