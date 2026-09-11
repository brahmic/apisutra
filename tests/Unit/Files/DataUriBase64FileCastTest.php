<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Tests\Stubs\Dto\DataUriBase64FileDto;
use Brahmic\ApiSutra\VO\Files\Base64File;

describe('DataUriBase64FileCast', function () {
    it('гидрирует чистый base64 в Base64File', function () {
        $cast = new DataUriBase64FileCast();

        $file = $cast->hydrate(base64_encode('data'));

        expect($file)->toBeInstanceOf(Base64File::class)
            ->and($file?->content())->toBe('data');
    });

    it('гидрирует data-uri с пробелом после запятой', function () {
        $cast = new DataUriBase64FileCast();

        $file = $cast->hydrate('data:image/jpeg;base64, ' . base64_encode('image'));

        expect($file)->toBeInstanceOf(Base64File::class)
            ->and($file?->content())->toBe('image');
    });

    it('сериализует Base64File обратно в чистый base64', function () {
        $cast = new DataUriBase64FileCast();
        $file = new Base64File(base64_encode('payload'));

        expect($cast->serialize($file))->toBe(base64_encode('payload'));
    });

    it('бросает ошибку на невалидный base64 payload', function () {
        $cast = new DataUriBase64FileCast();

        expect(fn () => $cast->hydrate('data:image/jpeg;base64, !!!'))
            ->toThrow(ConfigurationException::class, 'невалидный base64 payload');
    });

    it('работает через обычный DTO hydration path', function () {
        $dto = DataUriBase64FileDto::from([
            'document' => 'data:image/png;base64, ' . base64_encode('png'),
        ]);

        expect($dto->document)->toBeInstanceOf(Base64File::class)
            ->and($dto->document?->content())->toBe('png');
    });
});
