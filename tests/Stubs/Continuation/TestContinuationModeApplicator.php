<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Continuation;

use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class TestContinuationModeApplicator implements ContinuationModeApplicatorInterface
{
    #[\Override]
    public function apply(
        RequestInterface $request,
        RequestPartsBag $parts,
        ContinuationMode $mode,
        ?PipelineContext $context = null,
    ): RequestPartsBag {
        if (($parts->query['manual_async']['value'] ?? null) === true && $mode === ContinuationMode::Sync) {
            throw new ContinuationConfigurationException('Конфликт mode и ручного provider-флага async');
        }

        $parts->query['provider_async'] = [
            'value' => $mode === ContinuationMode::Async,
            'format' => null,
        ];

        return $parts;
    }
}
