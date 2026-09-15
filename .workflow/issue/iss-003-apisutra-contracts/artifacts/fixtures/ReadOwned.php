<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/owned')]
#[Returns(ConstructorOwned::class, unwrap: 'data')]
final class ReadOwned extends AbstractRequest {}
