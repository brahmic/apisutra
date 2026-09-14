<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final class ReviewNestedDto
{
    #[Nested(type: ReviewAddressDto::class)]
    public ReviewAddressDto $address;
}
