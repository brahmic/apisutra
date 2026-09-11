<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use RuntimeException;

final class FlakyTransportException extends RuntimeException
{
}
