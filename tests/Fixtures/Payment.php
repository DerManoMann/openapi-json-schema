<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(typeProperty: 'type', mapping: ['card' => CardPayment::class, 'bank' => BankPayment::class])]
abstract class Payment
{
    public float $amount;
}
