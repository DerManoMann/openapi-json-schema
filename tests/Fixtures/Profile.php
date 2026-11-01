<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

class Profile
{
    #[Groups(['read'])]
    public int $id;

    #[Groups(['read', 'write'])]
    #[SerializedName('display_name')]
    public string $displayName;

    #[Groups(['write'])]
    public string $password;
}
