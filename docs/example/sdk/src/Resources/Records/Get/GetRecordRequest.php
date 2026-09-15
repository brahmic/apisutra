<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use Brahmic\ApiSutra\Attributes\Behavior\Retry;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

// HTTP-метод и адрес операции.
#[Get('/records/{id}')]
// Повторы при временных ошибках API: до 3 попыток, включая первую.
#[Retry(attempts: 3)]
// Преобразовать содержимое поля data в типизированный DTO.
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(
        // Подставить id в {id} адреса запроса.
        #[Path]
        public int $id,
    ) {
    }
}
