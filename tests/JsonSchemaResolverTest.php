<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests;

use OpenApi\Assembler;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Resolver;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Utils\TypedList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\JsonSchema\JsonSchemaResolver;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Account;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Envelope;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Generics;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\OrderController;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Ticket;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\Yaml\Yaml;

final class JsonSchemaResolverTest extends TestCase
{
    public function testDescribesReferencedClassesWithoutAttributes(): void
    {
        $result = (new Builder())
            ->setMode(Mode::SPEC)
            ->addSource(new \ReflectionClass(OrderController::class))
            ->withResolver(static function (Resolver $resolver): void {
                $resolver->withResolvers(static function (TypedList $resolvers): void {
                    $resolvers->add(new JsonSchemaResolver());
                });
            })
            ->build();

        $this->assertTrue($result->isValid());
        $this->assertEquals(Yaml::parseFile(__DIR__ . '/Fixtures/orders.yaml'), $result->toArray());
    }

    public function testDeclinesWhatItCannotDescribe(): void
    {
        $resolver = new JsonSchemaResolver();
        $assembler = new Assembler();

        $this->assertFalse($resolver->resolve('Not\\A\\Class', $assembler));
        $this->assertFalse($resolver->resolve(\DateTimeImmutable::class, $assembler));
        $this->assertSame([], $assembler->getSpecification()->schemas);
    }

    public function testEnumsAreInline(): void
    {
        $schema = $this->resolve(Ticket::class);

        $this->assertSame(['id', 'status', 'priority', 'colour'], $schema->required);
        $this->assertSame([
            'id' => [null, 'integer'],
            'status' => [['open', 'shipped'], 'string'],
            'priority' => [[1, 2], 'integer'],
            'colour' => [['Red', 'Green'], 'string'],
            'previousStatus' => [['open', 'shipped', null], ['string', 'null']],
        ], $this->propertyMap($schema, static fn (OA\Schema $property): array => [$property->enum, $property->type]));
    }

    /**
     * Symfony describes a generic class once, whatever its type arguments, and a template type as any value.
     */
    public function testGenericsLoseTheirTypeArguments(): void
    {
        $this->assertSame(
            array_fill_keys(['text', 'number', 'customer', 'nested'], Envelope::class),
            $this->propertyMap($this->resolve(Generics::class), static fn (OA\Schema $property): mixed => $property->ref),
        );

        $envelope = $this->resolve(Envelope::class);
        $this->assertSame(
            ['data' => [null, null], 'history' => ['array', null]],
            $this->propertyMap($envelope, static fn (OA\Schema $property): array => [$property->type, $property->items instanceof OA\Schema ? $property->items->type : null]),
        );
    }

    /**
     * @return iterable<string, array{Direction|null, array<string, array{bool|null, bool|null}>}>
     */
    public static function directions(): iterable
    {
        yield 'none' => [null, ['name' => [null, null], 'id' => [true, null], 'password' => [null, true]]];
        yield 'input' => [Direction::Input, ['name' => [null, null], 'password' => [null, null]]];
        yield 'output' => [Direction::Output, ['name' => [null, null], 'id' => [null, null]]];
    }

    /**
     * @param array<string, array{bool|null, bool|null}> $expected property name => readOnly, writeOnly
     */
    #[DataProvider('directions')]
    public function testDirection(?Direction $direction, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->propertyMap($this->resolve(Account::class, $direction), static fn (OA\Schema $property): array => [$property->readOnly, $property->writeOnly]),
        );
    }

    /**
     * @param class-string $fqcn
     */
    protected function resolve(string $fqcn, ?Direction $direction = null): OA\Schema
    {
        $specification = new Specification();

        $this->assertTrue((new JsonSchemaResolver($direction))->resolve($fqcn, new Assembler($specification)));
        $this->assertCount(1, $specification->schemas);
        $this->assertSame($fqcn, $specification->schemas[0]->getClassName());

        return $specification->schemas[0];
    }

    /**
     * @param \Closure(OA\Schema): mixed $map
     *
     * @return array<string, mixed>
     */
    protected function propertyMap(OA\Schema $schema, \Closure $map): array
    {
        $properties = [];
        foreach ($schema->properties ?? [] as $property) {
            $this->assertInstanceOf(OA\Schema::class, $property->schema);
            $properties[(string) $property->property] = $map($property->schema);
        }

        return $properties;
    }
}
