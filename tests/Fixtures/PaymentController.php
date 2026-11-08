<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use OpenApi\Spec as OA;

#[OA\Info(title: 'Payments', version: '1.0')]
class PaymentController
{
    #[OA\Operation\Get(path: '/payments/{id}')]
    #[OA\Parameter\Path(name: 'id', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'The payment', content: new OA\MediaType\Json(schema: new OA\Schema(ref: Payment::class)))]
    public function get(int $id): void
    {
    }
}
