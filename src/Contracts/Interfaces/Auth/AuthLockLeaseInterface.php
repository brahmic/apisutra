<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Auth;

interface AuthLockLeaseInterface
{
    /** Атомарно освобождает только собственную блокировку; false при утрате владения. */
    public function release(): bool;
}
