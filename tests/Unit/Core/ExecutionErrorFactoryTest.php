<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Execution\ExecutionErrorFactory;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

describe('ExecutionErrorFactory', function () {
    it('создаёт результат для исключения', function () {
        $factory = new ExecutionErrorFactory();
        $request = new SimpleGetRequest('q');
        $exception = new RuntimeException('Ошибка');

        $result = $factory->buildExceptionResult($request, $exception);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ConnectionFailed);
        expect($result->errors->first()?->message)->toBe('Ошибка');
        expect($result->exception)->toBe($exception);
    });

    it('формирует сообщения об ошибочных элементах', function () {
        $factory = new ExecutionErrorFactory();

        $message = $factory->unsupportedItemMessage('items', 2, 'foo');
        $invalid = $factory->invalidItemMessage('items', 3, new SimpleGetRequest('q'));

        expect($message)->toContain('items[2]');
        expect($message)->toContain('string(foo)');
        expect($invalid)->toContain('items[3]');
        expect($invalid)->toContain(SimpleGetRequest::class);
    });
});
