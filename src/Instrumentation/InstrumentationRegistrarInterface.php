<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
namespace Mumzworld\OpenTelemetry\Instrumentation;

/**
 * Interface for instrumentation registrar classes.
 *
 * Each registrar class is responsible for grouping and registering
 * related OpenTelemetry instrumentations (e.g., Cache, CLI, HTTP).
 */
interface InstrumentationRegistrarInterface
{
    /**
     * Register all instrumentations for a given domain
     *
     * @return void
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function register(): void;
    //phpcs:enable Magento2.Functions.StaticFunction
}
