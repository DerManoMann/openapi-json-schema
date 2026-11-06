<?php declare(strict_types=1);

namespace Radebatz\OpenApi\JsonSchema\Tests;

use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Resolver;
use OpenApi\Utils\TypedList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\JsonSchema\JsonSchemaPlugin;
use Radebatz\OpenApi\JsonSchema\JsonSchemaResolver;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\ArticleController;
use Radebatz\OpenApi\JsonSchema\Tests\Fixtures\OrderController;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\Yaml\Yaml;

final class JsonSchemaPluginTest extends TestCase
{
    public function testAddsTheResolver(): void
    {
        $result = (new Builder())
            ->setMode(Mode::SPEC)
            ->addSource(new \ReflectionClass(OrderController::class))
            ->withPlugins([JsonSchemaPlugin::class])
            ->build();

        $this->assertTrue($result->isValid());
        $this->assertEquals(Yaml::parseFile(__DIR__ . '/Fixtures/orders.yaml'), $result->toArray());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, JsonSchemaResolver}>
     */
    public static function settings(): iterable
    {
        yield 'none' => [[], new JsonSchemaResolver()];
        yield 'direction' => [['direction' => 'output'], new JsonSchemaResolver(Direction::Output)];
        yield 'direction case' => [['direction' => Direction::Output], new JsonSchemaResolver(Direction::Output)];
        yield 'groups' => [['groups' => ['summary']], new JsonSchemaResolver(groups: ['summary'])];
        yield 'one group' => [['groups' => 'summary'], new JsonSchemaResolver(groups: ['summary'])];
        yield 'qualifyAttributedClasses' => [['qualifyAttributedClasses' => true], new JsonSchemaResolver(qualifyAttributedClasses: true)];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('settings')]
    public function testSettingsAreTheResolversArguments(array $settings, JsonSchemaResolver $jsonSchemaResolver): void
    {
        $expected = $this->builder()
            ->withResolver(static function (Resolver $resolver) use ($jsonSchemaResolver): void {
                $resolver->withResolvers(static function (TypedList $resolvers) use ($jsonSchemaResolver): void {
                    $resolvers->add($jsonSchemaResolver);
                });
            })
            ->build();

        $result = $this->builder()
            ->withPlugins([JsonSchemaPlugin::class => $settings])
            ->build();

        $this->assertTrue($result->isValid());
        $this->assertEquals($expected->toArray(), $result->toArray());
        if ($settings !== []) {
            $this->assertNotEquals($this->builder()->withPlugins([JsonSchemaPlugin::class])->build()->toArray(), $result->toArray());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'unknown' => [['group' => 'read'], 'Unknown setting "group"'];
        yield 'direction' => [['direction' => 'both'], 'Invalid value "both" for the "direction" setting'];
        yield 'direction not a string' => [['direction' => 1], 'Invalid value int for the "direction" setting'];
        yield 'groups not strings' => [['groups' => [1]], 'Invalid value array for the "groups" setting'];
        yield 'groups not a list' => [['groups' => ['read' => 'read']], 'Invalid value array for the "groups" setting'];
        yield 'qualifyAttributedClasses' => [['qualifyAttributedClasses' => 'yes'], 'Invalid value "yes" for the "qualifyAttributedClasses" setting'];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('invalidSettings')]
    public function testThrowsForSettingsItCannotUse(array $settings, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new Builder())->withPlugins([JsonSchemaPlugin::class => $settings]);
    }

    /**
     * Orders show the direction, articles the groups and the attributed classes.
     */
    protected function builder(): Builder
    {
        return (new Builder())
            ->setMode(Mode::SPEC)
            ->addSource(new \ReflectionClass(OrderController::class))
            ->addSource(new \ReflectionClass(ArticleController::class));
    }
}
