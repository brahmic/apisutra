<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\TransportOptions;

final class TransportCapabilities
{
    public static function check(TransportInterface $transport, TransportOptions $options): void
    {
        if ($transport instanceof TimeoutAwareTransportInterface) {
            $transport->assertSupportsTimeouts($options);
        } elseif ($options->hasLimits()) {
            throw new ConfigurationException($transport::class . ' не поддерживает timeout/connectTimeout/deadline');
        }
    }
}
