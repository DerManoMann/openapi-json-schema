# openapi-json-schema

[![Build Status](https://github.com/DerManoMann/openapi-json-schema/actions/workflows/build.yml/badge.svg)](https://github.com/DerManoMann/openapi-json-schema/actions/workflows/build.yml)
[![Coverage Status](https://coveralls.io/repos/github/DerManoMann/openapi-json-schema/badge.svg)](https://coveralls.io/github/DerManoMann/openapi-json-schema)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Introduction

A request or response often uses a class that has no OpenAPI attributes: a DTO, an entity, a
class from another package. [swagger-php](https://github.com/zircote/swagger-php) can only
describe a class through its attributes, so the class has to be annotated or the reference
stays unresolved.

openapi-json-schema describes such a class with the schema
[symfony/json-schema](https://github.com/symfony/json-schema) generates for it. That schema
comes from what Symfony already knows about the class: property types and docblocks through
PropertyInfo, and with `symfony/serializer` and `symfony/validator` installed, serializer groups,
name converters, discriminator maps and validator constraints.

**`symfony/json-schema` is experimental.** It ships with Symfony 8.2 and has no backwards
compatibility promise yet. This package follows it and is experimental too. Once the
component is stable, this package might move into swagger-php as an optional part.

## Installation

```shell
composer require radebatz/openapi-json-schema
```

The package requires swagger-php 7.4 or later. Until `symfony/json-schema` has a stable release, the
project needs `"minimum-stability": "dev"` and `"prefer-stable": true` in its `composer.json`.

## Usage

`JsonSchemaPlugin` adds a `JsonSchemaResolver` to the builder, after swagger-php's own resolver:

```php
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use Radebatz\OpenApi\JsonSchema\JsonSchemaPlugin;

$result = (new Builder())
    ->setMode(Mode::SPEC)
    ->addSource('src')
    ->withPlugins([JsonSchemaPlugin::class])
    ->build();
```

On the command line, the same is `--plugin "Radebatz\OpenApi\JsonSchema\JsonSchemaPlugin"`.
Plugins are not used in classic mode.

The plugin's settings are the resolver's options, described below: `direction`, `groups` and
`qualifyAttributedClasses`. A setting it does not know, or a value it cannot use, throws.

```php
->withPlugins([JsonSchemaPlugin::class => ['direction' => 'output', 'groups' => ['read']]])
```

To add the resolver yourself, for example to place it elsewhere in the chain:

```php
use OpenApi\Resolver;
use OpenApi\Utils\TypedList;
use Radebatz\OpenApi\JsonSchema\JsonSchemaResolver;

$builder->withResolver(fn (Resolver $resolver) => $resolver->withResolvers(
    fn (TypedList $resolvers) => $resolvers->add(new JsonSchemaResolver()),
));
```

A class referenced by its name, for example `new OA\Schema(ref: Order::class)`, is then
described like this:

* **A class with attributes is described by its attributes.** swagger-php's own resolver runs
  first, and this one only sees what that left unresolved.
* **A class without attributes becomes a component** with the schema Symfony generates.
* **The classes it references are resolved the same way.** Each is referenced by its name, so
  it gets its own component, from its attributes where it has them.
* **swagger-php names the components.** The names Symfony picks for its definitions are not
  used.
* **PHP's own classes are not components.** Symfony describes a `DateTimeImmutable` inline
  where it is used.
* **A generic class is described once.** `Envelope<string>` and `Envelope<int>` both reference
  the same `Envelope` component, and a template type like `T` allows any value.

### Direction

By default one schema describes a class for both requests and responses, and marks a property
that can only be read or only be written with `readOnly` or `writeOnly`. To describe classes
for one direction only, set `direction` to `input` or `output`, or a `Direction` case:

```php
JsonSchemaPlugin::class => ['direction' => 'output']

// or, adding the resolver yourself
new JsonSchemaResolver(Symfony\Component\JsonSchema\Direction::Output);
```

### Serializer and validator

`symfony/json-schema` uses the serializer and the validator when they are installed. Nothing needs
setting up here. With `symfony/serializer`, a schema describes what the serializer writes and reads:

* A `#[SerializedName]` renames the property.
* A `#[DiscriminatorMap]` becomes an `anyOf` of the mapped classes, each requiring the type property.

To describe only the properties of some serializer groups, set `groups`:

```php
JsonSchemaPlugin::class => ['groups' => ['read']]

// or, adding the resolver yourself
new JsonSchemaResolver(groups: ['read']);
```

The groups apply to every class in the document, unless a reference asks for others (see below).
A property in none of them is left out, as the serializer leaves it out.

With `symfony/validator` installed, constraints become keywords: `Length` becomes `minLength` and
`maxLength`, `Range` becomes `minimum` and `maximum`, and `NotBlank` makes the property required.

**A discriminator map** makes the base class the `anyOf` of the mapped classes. Each mapped class
already has the base class's properties in it, so swagger-php does not add the base class to its
`allOf`.

### Describing a class more than once

A reference can ask for its own groups or direction with the qualifiers of a `Schema\Ref`:

```php
new OA\Schema\Ref(ref: Article::class, qualifiers: ['groups' => ['summary']])
```

The class is then described with these settings instead of the resolver's, as a component of
its own, next to the one plain references use. Qualifiers left out fall back to the resolver's
settings.

* **`groups`** takes a list of serializer groups, or one group as a string.
* **`direction`** takes `input` or `output`.

The classes a qualified class references keep its qualifiers, so `Article` with the `summary`
group references `Author` with the `summary` group too. swagger-php names each component after
its key, for example `Article-groups.summary`.

A referenced class with OpenAPI attributes is the exception. It is referenced plain and described
by its attributes, as anywhere else in the document. To give it the qualifiers too, and describe
it with the schema Symfony generates, set `qualifyAttributedClasses`:

```php
JsonSchemaPlugin::class => ['qualifyAttributedClasses' => true]

// or, adding the resolver yourself
new JsonSchemaResolver(qualifyAttributedClasses: true);
```

A reference that puts qualifiers on a class with attributes itself is described by this resolver,
since swagger-php's own resolver only handles plain references. A reference with any other
qualifier is left to the next resolver.

## License

The openapi-json-schema project is released under the [MIT license](LICENSE).
