<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

enum Status: string
{
    case Open = 'open';
    case Shipped = 'shipped';
}
