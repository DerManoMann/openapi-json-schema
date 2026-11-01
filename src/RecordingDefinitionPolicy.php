<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * Names definitions like the policy it wraps, and remembers which class each name stands for.
 *
 * Symfony keeps that mapping to itself. The resolver needs it to turn a reference to a
 * definition back into the class it describes.
 */
class RecordingDefinitionPolicy implements DefinitionPolicyInterface
{
    /** @var \WeakMap<SchemaDocument, array<string, class-string>> */
    protected \WeakMap $classes;

    public function __construct(protected DefinitionPolicyInterface $policy = new ShortNameDefinitionPolicy())
    {
        $this->classes = new \WeakMap();
    }

    public function nameFor(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string
    {
        $name = $this->policy->nameFor($class, $config, $document, $parent);

        $classes = $this->classes[$document] ?? [];
        $classes[$name] = $class;
        $this->classes[$document] = $classes;

        return $name;
    }

    /**
     * @return array<string, class-string> the class of each definition named in the document so far
     */
    public function classes(SchemaDocument $document): array
    {
        return $this->classes[$document] ?? [];
    }
}
