<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Core;

use Magento\Framework\Event\InvokerInterface;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class EventObserverInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.event-observer';
    private const SPAN_NAME_PREFIX = 'Event:';
    private const EXCLUDED_OBSERVERS = [
        'pagecache',
        'catalogrule',
        'magento_currencysymbol_currency_display_options',
        'inventory'
    ];

    /**
     * @inheritdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    protected static function getInstrumentationName(): string
    {
        return self::INSTRUMENTATION_NAME;
    }

    /**
     * @inheritdoc
     */
    public static function register(): void
    {
        self::instrumentEventInvokerDispatch();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentEventInvokerDispatch(): void
    {
        hook(
            InvokerInterface::class,
            'dispatch',
            static function (
                InvokerInterface     $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $observerConfig = $params[0] ?? [];
                $observerName = $observerConfig['name'] ?? 'NA';
                if (in_array($observerName, self::EXCLUDED_OBSERVERS)) {
                    return;
                }

                $spanName = sprintf('%s Observer: %s', self::SPAN_NAME_PREFIX, $observerName);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute('magento.event.observer.invoked.details', self::saferJsonEncode($observerConfig));

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                InvokerInterface     $subject,
                array       $params,
                mixed       $returnValue,
                ?Throwable  $exception,
            ) {
                $observerConfig = $params[0] ?? [];
                $observerName = $observerConfig['name'] ?? 'NA';
                if (in_array($observerName, self::EXCLUDED_OBSERVERS)) {
                    return;
                }
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
