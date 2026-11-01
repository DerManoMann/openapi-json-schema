<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

class Order
{
    public int $id;

    public Customer $customer;

    /** @var list<LineItem> */
    public array $items = [];

    public Status $status;

    public \DateTimeImmutable $createdAt;

    public ?Customer $billedTo = null;

    public ?Address $shippingAddress = null;
}
