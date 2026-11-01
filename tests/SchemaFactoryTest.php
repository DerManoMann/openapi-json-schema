<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests;

use OpenApi\Spec as OA;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\JsonSchema\SchemaFactory;

final class SchemaFactoryTest extends TestCase
{
    public function testNestedSchemas(): void
    {
        $schema = (new SchemaFactory())->create([
            'type' => 'object',
            'properties' => [
                'tags' => ['type' => 'array', 'items' => new \ArrayObject()],
                'point' => ['type' => 'array', 'prefixItems' => [['type' => 'number'], ['type' => 'number']]],
            ],
            'additionalProperties' => false,
            'patternProperties' => ['^x_' => ['type' => 'string']],
            'oneOf' => [['$ref' => '#/components/schemas/Cat'], ['$ref' => '#/elsewhere']],
            'discriminator' => ['propertyName' => 'kind', 'mapping' => ['cat' => '#/components/schemas/Cat']],
            'required' => ['tags'],
            'enum' => [['a' => 1]],
        ], static fn (string $ref): string => $ref === '#/components/schemas/Cat' ? 'App\\Cat' : $ref);

        $this->assertSame('object', $schema->type);
        $this->assertFalse($schema->additionalProperties);
        $this->assertSame(['tags'], $schema->required);
        $this->assertSame([['a' => 1]], $schema->enum, 'values stay values');

        [$tags, $point] = $schema->properties ?? [];
        $this->assertSame('tags', $tags->property);
        $this->assertInstanceOf(OA\Schema::class, $tags->schema?->items);
        $this->assertSame('point', $point->property);
        $this->assertContainsOnlyInstancesOf(OA\Schema::class, $point->schema->prefixItems ?? []);

        $this->assertInstanceOf(OA\Schema::class, $schema->patternProperties['^x_'] ?? null);
        $this->assertSame(['App\\Cat', '#/elsewhere'], array_map(static fn (OA\Schema $one): mixed => $one->ref, $schema->oneOf ?? []));

        $this->assertInstanceOf(OA\Discriminator::class, $schema->discriminator);
        $this->assertSame('kind', $schema->discriminator->propertyName);
        $this->assertSame(['cat' => '#/components/schemas/Cat'], $schema->discriminator->mapping, 'a mapping is not a $ref');
    }

    public function testExtensionsAndUnknownKeywords(): void
    {
        $schema = (new SchemaFactory())->create([
            'type' => 'string',
            'x-internal' => true,
            'x' => ['not' => 'an extension'],
            '$comment' => 'not a keyword of the spec model',
            'unknown' => 1,
        ], static fn (string $ref): string => $ref);

        $this->assertSame('string', $schema->type);
        $this->assertSame(['internal' => true], $schema->x);
    }
}
