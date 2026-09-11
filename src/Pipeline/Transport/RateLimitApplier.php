<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Transport;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class RateLimitApplier
{
    public function __construct(
        private ClientConfig $config,
        private RateLimiter $rateLimiter,
    ) {}

    public function apply(RequestInterface $request, PipelineContext $context): void
    {
        $config = $this->resolveRateLimitConfig($request, $context->options);
        if ($config === null) {
            return;
        }

        $key = $this->buildRateLimitKey($config, $context);
        $this->rateLimiter->acquire($config, $key, $context->budget);
        $context->budget?->check('rate_limit');
    }

    private function resolveRateLimitConfig(RequestInterface $request, ?RequestOptions $options): ?RateLimitConfig
    {
        if (!$request instanceof AbstractRequest) {
            return $this->config->rateLimit;
        }

        if ($options?->getRateLimitDisabledOverride() === true) {
            return null;
        }

        if ($options?->getRateLimitOverride() !== null) {
            $override = $options->getRateLimitOverride();
            return new RateLimitConfig(
                limit: $override->limit,
                period: $override->period,
                behavior: $override->behavior,
                store: $override->store ?? $this->config->rateLimit?->store,
                key: $override->key,
            );
        }

        if ($request->getRateLimitDisabledOverride() === true) {
            return null;
        }

        if ($request->getRateLimitOverride() !== null) {
            $override = $request->getRateLimitOverride();
            return new RateLimitConfig(
                limit: $override->limit,
                period: $override->period,
                behavior: $override->behavior,
                store: $override->store ?? $this->config->rateLimit?->store,
                key: $override->key,
            );
        }

        $attribute = $request->getRateLimitAttribute();
        if ($attribute !== null) {
            return new RateLimitConfig(
                limit: $attribute->limit,
                period: $attribute->period,
                behavior: $attribute->behavior,
                store: $this->config->rateLimit?->store,
                key: $attribute->key,
            );
        }

        return $this->config->rateLimit;
    }

    private function buildRateLimitKey(RateLimitConfig $config, PipelineContext $context): string
    {
        // Ключ задаётся по приоритету: override → атрибут → baseUrl (default).
        $rawKey = $config->key ?? $context->config->baseUrl;
        $algo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'sha256';

        return 'rate:' . hash($algo, $rawKey);
    }
}
