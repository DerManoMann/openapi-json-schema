# openapi-json-schema

[![Build Status](https://github.com/DerManoMann/openapi-json-schema/actions/workflows/build.yml/badge.svg)](https://github.com/DerManoMann/openapi-json-schema/actions/workflows/build.yml)
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
compatibility promise yet. This package follows it and is experimental too.

## Installation

```shell
composer require radebatz/openapi-json-schema
```

The package requires swagger-php 7. Until `symfony/json-schema` has a stable release, the
project needs `"minimum-stability": "dev"` and `"prefer-stable": true` in its `composer.json`.

## Usage

`JsonSchemaResolver` is a swagger-php resolver. Add it after swagger-php's own:

```php
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Resolver;
use OpenApi\Utils\TypedList;
use Radebatz\OpenApi\JsonSchema\JsonSchemaResolver;

$result = (new Builder())
    ->setMode(Mode::SPEC)
    ->addSource('src')
    ->withResolver(fn (Resolver $resolver) => $resolver->withResolvers(
        fn (TypedList $resolvers) => $resolvers->add(new JsonSchemaResolver()),
    ))
    ->build();
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
for one direction only, pass it to the resolver:

```php
use Symfony\Component\JsonSchema\Direction;

new JsonSchemaResolver(Direction::Output);
```

## License

The openapi-json-schema project is released under the [MIT license](LICENSE).
