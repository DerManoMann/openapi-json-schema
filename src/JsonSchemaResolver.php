<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema;

use OpenApi\Assembler;
use OpenApi\Contracts\ResolverInterface;
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
 * Add it after swagger-php's own resolver, so a class with attributes is still described by them:
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
 */
class JsonSchemaResolver implements ResolverInterface
{
    protected SchemaGenerator $generator;

    protected SchemaFactory $factory;

    /**
     * @param Direction|null $direction describe a class as input or output only; with none, one schema describes both through `readOnly` and `writeOnly`
     * @param list<string>   $groups    the serializer groups every class is described with; with none, every property is described
     */
    public function __construct(protected ?Direction $direction = null, protected array $groups = [])
    {
        $this->generator = SchemaGenerator::create();
        $this->factory = new SchemaFactory();
    }

    public function resolve(string $fqcn, Assembler $assembler): bool
    {
        // PHP's own classes, like DateTimeImmutable, are described inline where they are used, never as a component
        if ((!class_exists($fqcn) && !enum_exists($fqcn)) || (new \ReflectionClass($fqcn))->isInternal()) {
            return false;
        }

        $dialect = Dialect::openApi31();
        $document = new SchemaDocument();

        try {
            $schema = $this->generator->generate(
                enum_exists($fqcn) ? Type::enum($fqcn) : Type::object($fqcn),
                new Configuration(dialect: $dialect, groups: $this->groups, direction: $this->direction),
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

        $component = $this->factory->create($root, static function (string $ref) use ($dialect, $classes): string {
            $name = str_starts_with($ref, $dialect->refPath) ? substr($ref, strlen($dialect->refPath)) : null;

            return $classes[$name] ?? $ref;
        });

        $assembler->getSpecification()->add($component->setReflector(new \ReflectionClass($fqcn)));

        return true;
    }
}
