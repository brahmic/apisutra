<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\OperationDescriptorRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SkipCredentialsRequest;

describe('RequestSpecResolver', function () {
    it('не использует кеш при отключённом AttributeMetadataCache', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(false));

        $first = $resolver->resolveClass(SimpleGetRequest::class);
        $second = $resolver->resolveClass(SimpleGetRequest::class);

        expect($first === $second)->toBeFalse();
    });

    it('использует кеш при включённом AttributeMetadataCache', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(true));

        $first = $resolver->resolveClass(SimpleGetRequest::class);
        $second = $resolver->resolveClass(SimpleGetRequest::class);

        expect($first === $second)->toBeTrue();
    });

    it('очищает кеш через clearCache', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(true));

        $first = $resolver->resolveClass(SimpleGetRequest::class);
        RequestSpecResolver::clearCache();
        $second = $resolver->resolveClass(SimpleGetRequest::class);

        expect($first === $second)->toBeFalse();
    });

    it('резолвит флаг SkipCredentialsEnrichment', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(true));

        $spec = $resolver->resolveClass(SkipCredentialsRequest::class);

        expect($spec->skipCredentialsEnrichment)->toBeTrue();
    });

    it('резолвит ContinuationResult атрибут', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(true));

        $spec = $resolver->resolveClass(ContinuationStartRequest::class);

        expect($spec->continuationResult)->not->toBeNull()
            ->and($spec->continuationResult?->finalType)->toBe(ContinuationFinalDto::class);
    });

    it('резолвит OperationDescriptor атрибут', function () {
        $resolver = new RequestSpecResolver(new AttributeMetadataCache(true));

        $spec = $resolver->resolveClass(OperationDescriptorRequest::class);

        expect($spec->operationDescriptor)->not->toBeNull()
            ->and($spec->operationDescriptor?->title)->toBe('Operation title')
            ->and($spec->operationDescriptor?->description)->toBe('Operation description')
            ->and($spec->operationDescriptor?->note)->toBe('Operation note');
    });
});
