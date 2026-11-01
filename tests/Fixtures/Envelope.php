<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

/**
 * @template T
 */
class Envelope
{
    /** @var T */
    public mixed $data;

    /** @var list<T> */
    public array $history = [];
}
