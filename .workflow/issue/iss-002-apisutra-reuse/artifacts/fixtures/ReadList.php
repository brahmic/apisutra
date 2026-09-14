<?php

declare(strict_types=1);

namespace MaxSutraAudit;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/batches/1')]
#[Returns(RecordList::class, unwrap: 'data')]
final class ReadList extends AbstractRequest {}
