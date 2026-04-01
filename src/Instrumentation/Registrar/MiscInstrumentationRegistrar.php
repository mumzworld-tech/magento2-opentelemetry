<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Registrar;

use Mumzworld\OpenTelemetry\Instrumentation\Misc\AbstractDbInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\HttpClientInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\InventoryInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\PricingInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\RepositoryInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\SalesRuleInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\ShippingInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\InstrumentationRegistrarInterface;
use Mumzworld\OpenTelemetry\Instrumentation\Misc\TotalCollectorInstrumentation;

class MiscInstrumentationRegistrar implements InstrumentationRegistrarInterface
{
    /**
     * @inheirtdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function register(): void
    {
        SalesRuleInstrumentation::register();
        InventoryInstrumentation::register();
        ShippingInstrumentation::register();
        PricingInstrumentation::register();
        TotalCollectorInstrumentation::register();
        AbstractDbInstrumentation::register();
        RepositoryInstrumentation::register();
        HttpClientInstrumentation::register();
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
