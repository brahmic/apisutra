<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/records/{id}')]
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(
        #[Path]
        public int $id,
    ) {
    }
}
