<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/records')]
final class WriteRecord extends AbstractRequest
{
    public function __construct(
        #[Query('preview')] public bool $preview = false,
        #[Query('recipient_id', nullable: false)] public ?int $recipientId = null,
        #[Body] public string $title = '0',
        #[Body] public bool $active = false,
        #[Body] public ?string $link = null,
    ) {}
}
