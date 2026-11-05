<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests\Fixtures;

use OpenApi\Spec as OA;

#[OA\Info(title: 'Articles', version: '1.0')]
class ArticleController
{
    #[OA\Operation\Get(path: '/articles')]
    #[OA\Response(response: 200, description: 'The articles', content: new OA\MediaType\Json(schema: new OA\Schema(
        type: 'array',
        items: new OA\Schema\Ref(ref: Article::class, qualifiers: ['groups' => ['summary']]),
    )))]
    public function list(): void
    {
    }

    #[OA\Operation\Get(path: '/articles/{id}')]
    #[OA\Parameter\Path(name: 'id', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'The article', content: new OA\MediaType\Json(schema: new OA\Schema(ref: Article::class)))]
    public function get(int $id): void
    {
    }
}
