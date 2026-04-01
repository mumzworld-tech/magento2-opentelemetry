<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Registrar;

use Mumzworld\OpenTelemetry\Instrumentation\Cli\CliInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Cli\CronInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Cli\IndexerInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\InstrumentationRegistrarInterface;

class CliInstrumentationRegistrar implements InstrumentationRegistrarInterface
{
    /**
     * @inheirtdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function register(): void
    {
        CliInstrumentation::register();
        CronInstrumentation::register();
        IndexerInstrumentation::register();
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
