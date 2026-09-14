<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/records/1')]
#[Returns(Record::class, unwrap: 'data')]
final class ReadRecord extends AbstractRequest {}
