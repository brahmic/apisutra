<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootConflictBodyRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootConflictDuplicateRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootConflictFileRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootConflictIgnoreRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootConflictUnmappedBodyRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootGetScalarRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootPatchListRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BodyRootWithQueryDefaultsRequest;
use Brahmic\ApiSutra\VO\Files\FileInput;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('Serializer body root', function () {
    it('сериализует root list для patch-запроса', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $operations = [
            ['op' => 'replace', 'path' => '/params', 'value' => ['enabled' => true]],
        ];
        $request = new BodyRootPatchListRequest(
            id: '77',
            dryRun: true,
            mode: 'patch',
            operations: $operations,
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('/body-root/77')
            ->and($prepared->url)->toContain('dryRun=1')
            ->and($prepared->headers['X-Mode'] ?? null)->toBe('patch')
            ->and($prepared->meta['bodyIsRoot'] ?? null)->toBeTrue()
            ->and($prepared->meta['body'])->toBe($operations)
            ->and($body)->toBe($operations);
    });

    it('поддерживает root scalar на GET (advanced)', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new BodyRootGetScalarRequest('abc', 'payload');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('q=abc')
            ->and($prepared->body)->toBe('"payload"')
            ->and($prepared->meta['bodyIsRoot'] ?? null)->toBeTrue();
    });

    it('сочетается с RequestDefaults Query для не-root полей', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $operations = [['op' => 'remove', 'path' => '/value']];
        $request = new BodyRootWithQueryDefaultsRequest(
            operations: $operations,
            plain: 'query-value',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('plain=query-value')
            ->and($prepared->meta['body'])->toBe($operations)
            ->and($prepared->meta['query']['plain']['value'] ?? null)->toBe('query-value');
    });

    it('бросает ошибку при конфликте BodyRoot и Body', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictBodyRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            extra: 'x',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot не может использоваться вместе с Body');
    });

    it('бросает ошибку при конфликте BodyRoot и body по умолчанию', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictUnmappedBodyRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            plain: 'x',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot конфликтует с полем, которое попадает в body по умолчанию');
    });

    it('бросает ошибку при конфликте BodyRoot и File', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictFileRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            file: FileInput::fromContent('file', 'f.txt'),
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot не может использоваться вместе с File');
    });

    it('бросает ошибку при дублировании BodyRoot', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictDuplicateRequest('a', 'b');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'Найдено более одного BodyRoot');
    });

    it('бросает ошибку при сочетании BodyRoot и Ignore на одном свойстве', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictIgnoreRequest('payload');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot не может использоваться вместе с Ignore');
    });
});
