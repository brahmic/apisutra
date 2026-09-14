<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class ReviewAddressDto
{
    public function __construct(public string $city)
    {
    }
}
