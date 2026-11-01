<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

class Account
{
    public string $name;

    private int $id = 0;

    private string $password = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
