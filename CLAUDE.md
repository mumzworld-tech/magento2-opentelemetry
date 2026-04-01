# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Composer library package (`mumzworld/magento2-opentelemetry`) that adds OpenTelemetry tracing to Magento 2. It hooks into Magento core classes at runtime to create spans for HTTP requests, database queries, cache operations, CLI commands, entity operations, and business logic (pricing, shipping, inventory, sales rules).

This is **not** a Magento module — it has no `etc/module.xml` or DI configuration. It bootstraps entirely via composer's `autoload.files` array through `_register.php`.

## Architecture

### Bootstrap Flow

1. Composer autoloads `_register.php` on every request
2. `InstrumentationGuard::isInstrumentationEligible()` checks prerequisites (OTel SDK available, `ext-opentelemetry` loaded, not running in phpunit/phpstan, request path is instrumentable)
3. Six domain registrars are called in sequence: Core, Cache, Database, Cli, Http, Entity
4. Each registrar instantiates its instrumentation classes and calls `register()`

### Hook-Based Instrumentation

All instrumentation classes extend `AbstractInstrumentation` and use OpenTelemetry's PHP `hook()` function to intercept Magento class methods:

```php
hook($className, $methodName, pre: $beforeCallback, post: $afterCallback);
```

The `pre` callback starts a span and stores it in context; the `post` callback ends the span and records any exceptions.

### Key Patterns

- **Registrar pattern** — `src/Instrumentation/Registrar/` classes implement `InstrumentationRegistrarInterface` and group related instrumentations by domain (Core, Http, Database, Cache, Cli, Entity, Misc)
- **Guard pattern** — `InstrumentationGuard` centralizes all eligibility checks (OTel availability, excluded URLs, allowed paths)
- **SpanBuilderTrait** — shared span creation/lifecycle logic used by all instrumentation classes
- **AttributeSanitizerTrait** — UTF-8 validation, string truncation (2048 chars), safe JSON encode/decode
- **SpanLimiter** — rate-limits span creation for high-frequency operations to prevent trace explosion

### Directory Layout

```
src/Instrumentation/
├── Core/          # Magento bootstrap, event observers, profiler
├── Http/          # REST, GraphQL, Backend admin, HTTP client
├── Database/      # SQL query tracing
├── Cache/         # Page cache, Redis, Varnish
├── Cli/           # CLI commands, cron, indexer
├── Entity/        # EAV and flat entity load/save
├── Misc/          # Business logic (pricing, shipping, inventory, sales rules, repositories)
├── Registrar/     # Domain registrars
└── Util/          # RequestHandler, ResponseHandler, GraphQlQueryParser, traits
```

## Requirements

- PHP ^8.1
- `ext-opentelemetry` PHP extension (checked at runtime)
- `magento/framework: *`
- OpenTelemetry PHP packages: `open-telemetry/api`, `open-telemetry/sdk`, `open-telemetry/exporter-otlp`, `open-telemetry/sem-conv` (all ^1.2+)

## Development Notes

- No test suite, linting, or CI pipeline exists yet
- All files use `declare(strict_types=1)` and PSR-4 autoloading under `Mumzworld\OpenTelemetry\`
- All instrumentation is wrapped in try-catch to never break the host Magento application
- The `README.md` contains setup/installation instructions and optimization tips
