<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use OpenApi\Spec as OA;

#[OA\Schema(description: 'Described by its attributes')]
class Address
{
    #[OA\Property]
    public string $street;
}
