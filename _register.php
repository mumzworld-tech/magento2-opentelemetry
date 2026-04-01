<?php

declare(strict_types=1);

use Mumzworld\OpenTelemetry\Instrumentation\InstrumentationGuard;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\CacheInstrumentationRegistrar;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\CliInstrumentationRegistrar;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\CoreInstrumentationRegistrar;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\DatabaseInstrumentationRegistrar;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\EntityInstrumentationRegistrar;
use Mumzworld\OpenTelemetry\Instrumentation\Registrar\HttpInstrumentationRegistrar;

if (!InstrumentationGuard::isInstrumentationEligible()) {
    return;
}

CoreInstrumentationRegistrar::register();
CacheInstrumentationRegistrar::register();
DatabaseInstrumentationRegistrar::register();
CliInstrumentationRegistrar::register();
HttpInstrumentationRegistrar::register();
EntityInstrumentationRegistrar::register();
