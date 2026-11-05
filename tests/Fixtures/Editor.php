<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use OpenApi\Spec as OA;
use Symfony\Component\Serializer\Attribute\Groups;

#[OA\Schema(description: 'An editor, described by its attributes.')]
class Editor
{
    #[OA\Property]
    #[Groups(['summary', 'full'])]
    public string $name;

    #[OA\Property]
    #[Groups(['full'])]
    public string $email;
}
