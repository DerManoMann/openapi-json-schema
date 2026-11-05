<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class Author
{
    #[Groups(['summary', 'full'])]
    public string $name;

    #[Groups(['full'])]
    public string $biography;
}
