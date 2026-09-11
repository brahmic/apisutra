<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Collections\Base64FileCollection;

final readonly class NestedDataUriFilesDto extends AbstractDto
{
    public function __construct(
        #[Nested(type: \Brahmic\ApiSutra\VO\Files\Base64File::class, itemCast: DataUriBase64FileCast::class)]
        public Base64FileCollection $faces,
    ) {}
}
