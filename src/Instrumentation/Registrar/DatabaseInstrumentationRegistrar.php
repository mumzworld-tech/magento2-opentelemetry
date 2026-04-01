<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Registrar;

use Mumzworld\OpenTelemetry\Instrumentation\Database\SqlInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\InstrumentationRegistrarInterface;

class DatabaseInstrumentationRegistrar implements InstrumentationRegistrarInterface
{
    /**
     * @inheirtdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function register(): void
    {
        SqlInstrumentation::register();
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
