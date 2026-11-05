<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class Article
{
    #[Groups(['summary', 'full'])]
    public string $title;

    #[Groups(['full'])]
    public string $body;

    #[Groups(['summary', 'full'])]
    public Author $author;

    #[Groups(['summary', 'full'])]
    public Editor $editor;
}
