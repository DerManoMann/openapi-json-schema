<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

class Generics
{
    /** @var Envelope<string> */
    public Envelope $text;

    /** @var Envelope<int> */
    public Envelope $number;

    /** @var Envelope<Customer> */
    public Envelope $customer;

    /** @var Envelope<Envelope<int>> */
    public Envelope $nested;
}
