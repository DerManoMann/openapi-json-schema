<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

class Customer
{
    /**
     * The customer's full name.
     */
    public string $name;

    public ?string $email = null;
}
