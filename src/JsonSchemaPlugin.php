<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema;

use OpenApi\Builder\Hooks;
use OpenApi\Contracts\PluginInterface;
use OpenApi\Resolver;
use OpenApi\Utils\TypedList;
use Symfony\Component\JsonSchema\Direction;

/**
 * Adds a `JsonSchemaResolver` after swagger-php's own resolver.
 *
 * ```php
 * (new Builder())->withPlugins([JsonSchemaPlugin::class => ['groups' => ['read']]]);
 * ```
 *
 * The settings are the resolver's constructor arguments, all optional:
 *
 * - `direction`: `input` or `output`, as for the `direction` qualifier, or a `Direction` case
 * - `groups`: a list of serializer groups, or one group as a string
 * - `qualifyAttributedClasses`: a bool
 *
 * A setting this plugin does not know, or a value it cannot use, throws an `\InvalidArgumentException`.
 */
class JsonSchemaPlugin implements PluginInterface
{
    public function configure(Hooks $hooks, array $config): void
    {
        $resolver = $this->resolver($config);

        $hooks->withResolver(static function (Resolver $chain) use ($resolver): void {
            $chain->withResolvers(static function (TypedList $resolvers) use ($resolver): void {
                $resolvers->add($resolver);
            });
        });
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolver(array $config): JsonSchemaResolver
    {
        $direction = null;
        $groups = [];
        $qualifyAttributedClasses = false;

        foreach ($config as $name => $value) {
            switch ($name) {
                case 'direction':
                    $direction = $value instanceof Direction ? $value : match ($value) {
                        'input' => Direction::Input,
                        'output' => Direction::Output,
                        default => throw $this->invalid($name, $value, '"input", "output" or a Direction case'),
                    };
                    break;
                case 'groups':
                    $groups = is_string($value) ? [$value] : $value;
                    if (!is_array($groups) || !array_is_list($groups) || array_filter($groups, is_string(...)) !== $groups) {
                        throw $this->invalid($name, $value, 'a list of strings, or a string');
                    }
                    break;
                case 'qualifyAttributedClasses':
                    if (!is_bool($value)) {
                        throw $this->invalid($name, $value, 'a bool');
                    }
                    $qualifyAttributedClasses = $value;
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf(
                        'Unknown setting "%s" for %s. Expected one of "direction", "groups", "qualifyAttributedClasses".',
                        $name,
                        static::class,
                    ));
            }
        }

        return new JsonSchemaResolver($direction, $groups, $qualifyAttributedClasses);
    }

    protected function invalid(string $name, mixed $value, string $expected): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf(
            'Invalid value %s for the "%s" setting of %s. Expected %s.',
            is_string($value) ? '"' . $value . '"' : get_debug_type($value),
            $name,
            static::class,
            $expected,
        ));
    }
}
