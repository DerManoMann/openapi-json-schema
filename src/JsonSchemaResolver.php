<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema;

use OpenApi\Assembler;
use OpenApi\Contracts\KeyResolverInterface;
use OpenApi\Resolver\Concerns\KeyResolverTrait;
use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\Exception\ExceptionInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\TypeInfo\Type;

/**
 * Describes a referenced class that has no OpenAPI attributes with the schema symfony/json-schema generates for it.
 *
 * `JsonSchemaPlugin` adds it. Added by hand, it goes after swagger-php's own resolver, so a class with attributes is still described by them:
 *
 * ```php
 * (new Builder())->withResolver(fn (Resolver $resolver) => $resolver->withResolvers(
 *     fn (TypedList $resolvers) => $resolvers->add(new JsonSchemaResolver()),
 * ));
 * ```
 *
 * Only the class asked for becomes a component. A class it references is referenced by name
 * and resolved like any other, so it is described by its attributes if it has them, and by
 * this resolver otherwise. swagger-php names the components; the names Symfony picks are dropped.
 *
 * A key's `groups` and `direction` qualifiers describe the class with those settings instead
 * of the constructor's, and the classes it references keep the qualifiers, apart from one with
 * OpenAPI attributes, which is referenced plain and described by them:
 *
 * ```php
 * new OA\Schema\Ref(ref: Author::class, qualifiers: ['groups' => ['summary'], 'direction' => 'output'])
 * ```
 *
 * A key with any other qualifier is left to the next resolver.
 */
class JsonSchemaResolver implements KeyResolverInterface
{
    use KeyResolverTrait;

    protected SchemaGenerator $generator;

    protected SchemaFactory $factory;

    /**
     * @param Direction|null $direction                describe a class as input or output only; with none, one schema describes both through `readOnly` and `writeOnly`
     * @param list<string>   $groups                   the serializer groups every class is described with; with none, every property is described
     * @param bool           $qualifyAttributedClasses keep the qualifiers on a reference to a class with OpenAPI attributes too, so it is described by this resolver instead of its attributes
     */
    public function __construct(
        protected ?Direction $direction = null,
        protected array $groups = [],
        protected bool $qualifyAttributedClasses = false,
    ) {
        $this->generator = SchemaGenerator::create();
        $this->factory = new SchemaFactory();
    }

    public function resolveKey(ComponentKey $key, Assembler $assembler): bool
    {
        $fqcn = $key->class;

        // PHP's own classes, like DateTimeImmutable, are described inline where they are used, never as a component
        if ((!class_exists($fqcn) && !enum_exists($fqcn)) || (new \ReflectionClass($fqcn))->isInternal()) {
            return false;
        }

        $configuration = $this->configuration($key);
        if ($configuration === null) {
            return false;
        }
        [$direction, $groups] = $configuration;

        $dialect = Dialect::openApi31();
        $document = new SchemaDocument();

        try {
            $schema = $this->generator->generate(
                enum_exists($fqcn) ? Type::enum($fqcn) : Type::object($fqcn),
                new Configuration(dialect: $dialect, groups: $groups, direction: $direction),
                $document,
            );
        } catch (ExceptionInterface) {
            return false;
        }

        $classes = $document->getDefinitionClasses();
        $definitions = $schema->getDefinitions();
        $root = (array) $schema->getRoot();

        // the class asked for is usually a definition itself, with a root that only references it
        if (is_string($root['$ref'] ?? null) && count($root) === 1) {
            $root = (array) ($definitions[substr($root['$ref'], strlen($dialect->refPath))] ?? $root);
        }

        $component = $this->factory->create($root, function (string $ref) use ($dialect, $classes, $key, $assembler): string|OA\Schema\Ref {
            $name = str_starts_with($ref, $dialect->refPath) ? substr($ref, strlen($dialect->refPath)) : null;
            if (!isset($classes[$name])) {
                return $ref;
            }

            return $key->isPlain() || (!$this->qualifyAttributedClasses && $this->hasAttributes($classes[$name], $assembler))
                ? $classes[$name]
                : new OA\Schema\Ref(ref: $classes[$name], qualifiers: $key->qualifiers);
        });

        $component->setReflector(new \ReflectionClass($fqcn));
        if (!$key->isPlain()) {
            $component->setComponentKey($key);
        }

        $assembler->getSpecification()->add($component);

        return true;
    }

    /**
     * The direction and groups a key asks for, each falling back to the constructor's, or null for a key this resolver does not know.
     *
     * @return array{Direction|null, list<string>}|null
     */
    protected function configuration(ComponentKey $key): ?array
    {
        $direction = $this->direction;
        $groups = $this->groups;

        foreach ($key->qualifiers as $name => $value) {
            if ($name === 'groups') {
                $groups = array_map(strval(...), (array) $value);
            } elseif ($name === 'direction') {
                $direction = match ($value) {
                    'input' => Direction::Input,
                    'output' => Direction::Output,
                    default => null,
                };
                if ($direction === null) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return [$direction, $groups];
    }

    /**
     * Whether the class is described by its OpenAPI attributes, as swagger-php's own resolver decides it: collected, the specification has a component for it.
     *
     * @param class-string $fqcn
     */
    protected function hasAttributes(string $fqcn, Assembler $assembler): bool
    {
        $key = new ComponentKey($fqcn);
        if ($assembler->getSpecification()->buildComponentIndex()->hasKey($key)) {
            return true;
        }

        $assembler->collect(new \ReflectionClass($fqcn));

        return $assembler->getSpecification()->buildComponentIndex()->hasKey($key);
    }
}
