<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class Product
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    public string $name;

    #[Assert\Range(min: 1, max: 100)]
    public int $quantity;
}
