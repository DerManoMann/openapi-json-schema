<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

class Ticket
{
    public function __construct(
        public readonly int $id,
        public readonly Status $status,
        public readonly Priority $priority,
        public readonly Colour $colour,
        public readonly ?Status $previousStatus,
    ) {
    }
}
