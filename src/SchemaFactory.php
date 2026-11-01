<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema;

use OpenApi\Spec\AbstractAttribute;
use OpenApi\Spec as OA;

/**
 * Builds spec attributes from the schema arrays Symfony generates.
 *
 * Which keyword holds a nested schema is read from the attribute's constructor: its parameter
 * type, or for a list or map of schemas its `@param` type. A keyword swagger-php learns later
 * is picked up without a change here.
 *
 * Keywords the attribute has no parameter for, like `$comment`, are dropped.
 */
class SchemaFactory
{
    /**
     * Parameters naming a component rather than describing it.
     *
     * The parameters every attribute shares, like `x`, are skipped as well: they are read from
     * `AbstractAttribute`, and extensions come from `x-` keywords only.
     */
    protected const IDENTITY = ['schema', 'component'];

    /** @var array<class-string, array<string, array{class-string<AbstractAttribute>|null, bool}>> */
    protected static array $parameters = [];

    /**
     * @param array<string, mixed>|\ArrayObject<string, mixed> $schema
     * @param \Closure(string): string                         $ref    maps a `$ref` to what the spec should reference
     */
    public function create(array|\ArrayObject $schema, \Closure $ref): OA\Schema
    {
        $attribute = $this->build(OA\Schema::class, (array) $schema, $ref);
        assert($attribute instanceof OA\Schema);

        return $attribute;
    }

    /**
     * @param class-string<AbstractAttribute> $class
     * @param array<string, mixed>            $values
     * @param \Closure(string): string        $ref
     */
    protected function build(string $class, array $values, \Closure $ref): AbstractAttribute
    {
        if ($class === OA\Schema::class) {
            $values = $this->refIntoAllOf($values);
        }

        $parameters = $this->parameters($class);
        $args = [];
        $x = [];

        foreach ($values as $keyword => $value) {
            if ($keyword === '$ref' && is_string($value)) {
                $args['ref'] = $ref($value);
            } elseif (str_starts_with((string) $keyword, 'x-')) {
                $x[substr((string) $keyword, 2)] = $value;
            } elseif ($keyword === 'properties' && $class === OA\Schema::class && is_array($value)) {
                $args['properties'] = array_map(fn (mixed $property): OA\Schema => $this->create($property, $ref), $value);
            } elseif (isset($parameters[$keyword])) {
                [$attributeClass, $isContainer] = $parameters[$keyword];
                $args[$keyword] = match (true) {
                    $attributeClass === null => $value,
                    $isContainer && is_array($value) => array_map(fn (mixed $item): mixed => $this->value($attributeClass, $item, $ref), $value),
                    default => $this->value($attributeClass, $value, $ref),
                };
            }
        }

        if ($x !== []) {
            $args['x'] = $x;
        }

        return new $class(...$args);
    }

    /**
     * Moves a `$ref` with keywords beside it into `allOf`.
     *
     * swagger-php keeps only some keywords beside a `$ref`, and which depends on the OpenAPI version.
     * Symfony writes `{"$ref": ..., "required": ["type"]}` for each class of a discriminator map.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function refIntoAllOf(array $values): array
    {
        if (!isset($values['$ref'])) {
            return $values;
        }

        foreach (array_keys($values) as $keyword) {
            if ($keyword !== '$ref' && !str_starts_with((string) $keyword, 'x-')) {
                $values['allOf'] = [['$ref' => $values['$ref']], ...(array) ($values['allOf'] ?? [])];
                unset($values['$ref']);

                return $values;
            }
        }

        return $values;
    }

    /**
     * @param class-string<AbstractAttribute> $class
     * @param \Closure(string): string        $ref
     */
    protected function value(string $class, mixed $value, \Closure $ref): mixed
    {
        return is_array($value) || $value instanceof \ArrayObject
            ? $this->build($class, (array) $value, $ref)
            : $value;
    }

    /**
     * The keyword parameters of an attribute, each with the attribute class it holds and whether it holds a list or map of them.
     *
     * @param class-string<AbstractAttribute> $class
     *
     * @return array<string, array{class-string<AbstractAttribute>|null, bool}>
     */
    protected function parameters(string $class): array
    {
        if (isset(static::$parameters[$class])) {
            return static::$parameters[$class];
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();
        $docComment = (string) $constructor?->getDocComment();
        $parameters = [];
        $skip = [...self::IDENTITY, ...$this->sharedParameters()];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            if (in_array($name, $skip, true)) {
                continue;
            }

            $parameters[$name] = $this->attributeClass($parameter->getType())
                ?? $this->containedClass($docComment, $name)
                ?? [null, false];
        }

        return static::$parameters[$class] = $parameters;
    }

    /**
     * The constructor parameters every attribute shares.
     *
     * @return list<string>
     */
    protected function sharedParameters(): array
    {
        $constructor = (new \ReflectionClass(AbstractAttribute::class))->getConstructor();

        return array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor?->getParameters() ?? []);
    }

    /**
     * @return array{class-string<AbstractAttribute>, false}|null
     */
    protected function attributeClass(?\ReflectionType $type): ?array
    {
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
        $classes = [];
        foreach ($types as $type) {
            if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), AbstractAttribute::class)) {
                $classes[] = $type->getName();
            }
        }

        // a schema slot may also take a subclass, like additionalProperties; the plain schema is the one to build
        $class = in_array(OA\Schema::class, $classes, true) ? OA\Schema::class : ($classes[0] ?? null);

        return $class !== null ? [$class, false] : null;
    }

    /**
     * The attribute class a `list<...>` or `array<string, ...>` parameter holds, from its `@param` type.
     *
     * @return array{class-string<AbstractAttribute>, true}|null
     */
    protected function containedClass(string $docComment, string $name): ?array
    {
        if (!preg_match('/@param\s+(\S+)\s+\$' . preg_quote($name, '/') . '\b/', $docComment, $matches)) {
            return null;
        }

        if (!preg_match('/^(?:list|array)<(?:string,\s*)?(\w+)>(?:\|null)?$/', $matches[1], $matches)) {
            return null;
        }

        $class = 'OpenApi\\Spec\\' . $matches[1];

        return is_subclass_of($class, AbstractAttribute::class) ? [$class, true] : null;
    }
}
