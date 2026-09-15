<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\MaxSutra\Model\Users\BotInfo;

#[Get('/me')]
#[Returns(BotInfo::class)]
final class ReadMe extends AbstractRequest {}
