<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\VO\Files\Base64File;

final readonly class DataUriBase64FileDto extends AbstractDto
{
    public function __construct(
        #[From('document')]
        #[Cast(DataUriBase64FileCast::class)]
        public ?Base64File $document = null,
    ) {}
}
