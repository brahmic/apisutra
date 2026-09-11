<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\OperationDescriptor;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/operation-descriptor')]
#[OperationDescriptor(
    title: 'Operation title',
    description: 'Operation description',
    note: 'Operation note',
)]
final class OperationDescriptorRequest extends AbstractRequest
{
}
