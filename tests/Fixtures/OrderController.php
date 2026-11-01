<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use OpenApi\Spec as OA;

#[OA\Info(title: 'Orders', version: '1.0')]
class OrderController
{
    #[OA\Operation\Get(path: '/orders/{id}')]
    #[OA\Parameter\Path(name: 'id', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'The order', content: new OA\MediaType\Json(schema: new OA\Schema(ref: Order::class)))]
    public function get(int $id): void
    {
    }
}
