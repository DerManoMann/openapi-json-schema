<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests;

use OpenApi\Assembler;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Resolver;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentKey;
use OpenApi\Utils\TypedList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\JsonSchema\JsonSchemaResolver;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Account;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\ArticleController;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Author;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\BankPayment;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\CardPayment;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Checkout;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Editor;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Envelope;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Generics;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\OrderController;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\PaymentController;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Product;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\Profile;
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

    /**
     * A class with attributes, like `Editor`, is referenced plain from a qualified class and described by its attributes.
     */
    public function testDescribesAClassPerReference(): void
    {
        $result = $this->buildArticles(new JsonSchemaResolver());

        $this->assertTrue($result->isValid());
        $this->assertEquals(Yaml::parseFile(__DIR__ . '/Fixtures/articles.yaml'), $result->toArray());
    }

    public function testQualifiesAttributedClassesWhenAsked(): void
    {
        $schemas = $this->buildArticles(new JsonSchemaResolver(qualifyAttributedClasses: true))->toArray()['components']['schemas'];

        $this->assertSame(['$ref' => '#/components/schemas/Editor-groups.summary'], $schemas['Article-groups.summary']['properties']['editor']);
        $this->assertSame(['type' => 'object', 'properties' => ['name' => ['type' => 'string']]], $schemas['Editor-groups.summary']);
    }

    public function testAnAttributedClassAlreadyCollectedIsNotCollectedAgain(): void
    {
        $result = $this->buildArticles(new JsonSchemaResolver(), Editor::class);

        $this->assertSame(['$ref' => '#/components/schemas/Editor'], $result->toArray()['components']['schemas']['Article-groups.summary']['properties']['editor']);
        $this->assertCount(1, array_filter($result->specification()->schemas, static fn (OA\Schema $schema): bool => $schema->getClassName() === Editor::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, array<string, array{bool|null, bool|null}>}>
     */
    public static function qualifiers(): iterable
    {
        yield 'groups' => [['groups' => ['summary']], ['name'], ['name' => [null, null]]];
        yield 'one group' => [['groups' => 'full'], ['name', 'biography'], ['name' => [null, null], 'biography' => [null, null]]];
        yield 'direction' => [['direction' => 'output'], ['name', 'id'], ['name' => [null, null], 'id' => [null, null]]];
    }

    /**
     * @param array<string, mixed>                       $qualifiers
     * @param list<string>                               $properties
     * @param array<string, array{bool|null, bool|null}> $expected   property name => readOnly, writeOnly
     */
    #[DataProvider('qualifiers')]
    public function testReadsTheKeysQualifiers(array $qualifiers, array $properties, array $expected): void
    {
        $key = new ComponentKey(isset($qualifiers['direction']) ? Account::class : Author::class, $qualifiers);
        $specification = new Specification();

        $this->assertTrue((new JsonSchemaResolver())->resolveKey($key, new Assembler($specification)));
        $this->assertCount(1, $specification->schemas);

        $schema = $specification->schemas[0];
        $this->assertEquals($key, $schema->getComponentKey());
        $this->assertSame($properties, array_keys($this->propertyMap($schema, static fn (OA\Schema $property): mixed => null)));
        $this->assertSame($expected, $this->propertyMap($schema, static fn (OA\Schema $property): array => [$property->readOnly, $property->writeOnly]));
    }

    public function testAPlainKeyGivesThePlainComponent(): void
    {
        $this->assertEquals(new ComponentKey(Author::class), $this->resolve(Author::class, groups: ['summary'])->getComponentKey());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unknownQualifiers(): iterable
    {
        yield 'other qualifier' => [['version' => 2]];
        yield 'other direction' => [['direction' => 'sideways']];
    }

    /**
     * @param array<string, mixed> $qualifiers
     */
    #[DataProvider('unknownQualifiers')]
    public function testLeavesUnknownQualifiersToTheNextResolver(array $qualifiers): void
    {
        $specification = new Specification();

        $this->assertFalse((new JsonSchemaResolver())->resolveKey(new ComponentKey(Author::class, $qualifiers), new Assembler($specification)));
        $this->assertSame([], $specification->schemas);
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
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function serializerGroups(): iterable
    {
        yield 'none' => [[], ['id', 'display_name', 'password']];
        yield 'read' => [['read'], ['id', 'display_name']];
        yield 'write' => [['write'], ['display_name', 'password']];
    }

    /**
     * @param list<string> $groups
     * @param list<string> $expected property names
     */
    #[DataProvider('serializerGroups')]
    public function testSerializerGroupsAndNames(array $groups, array $expected): void
    {
        $this->assertSame($expected, array_keys($this->propertyMap($this->resolve(Profile::class, groups: $groups), static fn (OA\Schema $property): mixed => $property->type)));
    }

    public function testValidatorConstraints(): void
    {
        $schema = $this->resolve(Product::class);

        $this->assertSame(['name'], $schema->required);
        $this->assertSame([
            'name' => [3, 50, null, null],
            'quantity' => [null, null, 1, 100],
        ], $this->propertyMap($schema, static fn (OA\Schema $property): array => [$property->minLength, $property->maxLength, $property->minimum, $property->maximum]));
    }

    /**
     * Symfony references each class of the map, and requires the property that tells them apart.
     */
    public function testDiscriminatorMap(): void
    {
        $payment = $this->propertyMap($this->resolve(Checkout::class), static fn (OA\Schema $property): mixed => $property->anyOf)['payment'];

        $this->assertSame(
            [[CardPayment::class, ['type']], [BankPayment::class, ['type']]],
            array_map(static fn (OA\Schema $one): array => [$one->allOf[0]->ref ?? null, $one->required], $payment),
        );

        $this->assertSame('card', $this->propertyMap($this->resolve(CardPayment::class), static fn (OA\Schema $property): mixed => $property->const)['type']);
    }

    /**
     * Symfony describes a mapped class with the base class's properties in it, so swagger-php does not add the base class to its `allOf`, which would reference the base class back.
     */
    public function testDiscriminatorMapHasNoCycle(): void
    {
        $result = (new Builder())
            ->setMode(Mode::SPEC)
            ->addSource(new \ReflectionClass(PaymentController::class))
            ->withResolver(static function (Resolver $resolver): void {
                $resolver->withResolvers(static function (TypedList $resolvers): void {
                    $resolvers->add(new JsonSchemaResolver());
                });
            })
            ->build();

        $this->assertTrue($result->isValid());

        $schemas = $result->toArray()['components']['schemas'];
        foreach (['CardPayment' => ['type', 'last4', 'amount'], 'BankPayment' => ['type', 'iban', 'amount']] as $name => $properties) {
            $this->assertArrayNotHasKey('allOf', $schemas[$name]);
            $this->assertSame($properties, array_keys($schemas[$name]['properties']));
        }
    }

    /**
     * @param class-string ...$sources more classes to scan
     */
    protected function buildArticles(JsonSchemaResolver $jsonSchemaResolver, string ...$sources): Builder\Result
    {
        $builder = (new Builder())
            ->setMode(Mode::SPEC)
            ->addSource(new \ReflectionClass(ArticleController::class))
            ->withResolver(static function (Resolver $resolver) use ($jsonSchemaResolver): void {
                $resolver->withResolvers(static function (TypedList $resolvers) use ($jsonSchemaResolver): void {
                    $resolvers->add($jsonSchemaResolver);
                });
            });

        foreach ($sources as $source) {
            $builder->addSource(new \ReflectionClass($source));
        }

        return $builder->build();
    }

    /**
     * @param class-string $fqcn
     * @param list<string> $groups
     */
    protected function resolve(string $fqcn, ?Direction $direction = null, array $groups = []): OA\Schema
    {
        $specification = new Specification();

        $this->assertTrue((new JsonSchemaResolver($direction, $groups))->resolve($fqcn, new Assembler($specification)));
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
