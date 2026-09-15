<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\MaxSutra\Model\Messages\Message;

#[Post('/messages')]
#[Returns(Message::class, unwrap: 'message')]
final class ReadMessage extends AbstractRequest {}
