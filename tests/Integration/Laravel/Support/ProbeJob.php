<?php

declare(strict_types=1);

namespace Integration;

use Integration\First\ItemsRequest;
use RuntimeException;

final readonly class ProbeJob
{
    public function __construct(private string $limit)
    {
    }

    public function handle(ItemsRequest $request): void
    {
        if ($request->limit !== '20') {
            throw new RuntimeException('Состояние запроса протекло между заданиями');
        }
        $request->limit = $this->limit;
        if (!$request->send()->raw()->isSuccess()) {
            throw new RuntimeException('SDK-запрос задания не выполнен');
        }
    }
}
