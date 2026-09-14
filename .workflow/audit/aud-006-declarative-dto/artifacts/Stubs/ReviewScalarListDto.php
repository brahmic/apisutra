<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class ReviewScalarListDto
{
    /** @param list<int> $ids */
    public function __construct(public array $ids)
    {
    }
}
