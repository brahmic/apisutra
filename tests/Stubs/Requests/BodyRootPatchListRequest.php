<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/{id}')]
final class BodyRootPatchListRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        #[Query('dryRun')]
        public bool $dryRun,
        #[Header('X-Mode')]
        public string $mode,
        #[BodyRoot]
        public array $operations,
    ) {}
}
