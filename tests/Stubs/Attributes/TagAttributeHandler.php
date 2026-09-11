<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Attributes;

use Brahmic\ApiSutra\Attributes\AttributeContext;
use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeContextHandlerInterface;

final class TagAttributeHandler implements AttributeContextHandlerInterface
{
    public function handle(AttributeContext $context): mixed
    {
        $data = is_array($context->data) ? $context->data : [];
        $data[] = $context->attribute->value;

        return $data;
    }
}
