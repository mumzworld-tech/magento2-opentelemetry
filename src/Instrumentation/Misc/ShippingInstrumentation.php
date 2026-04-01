<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Misc;

use Magento\Shipping\Model\Shipping;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class ShippingInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.shipping';
    private const SPAN_NAME_PREFIX = 'Shipping:';

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
        self::instrumentGetProductSalableQty();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentGetProductSalableQty(): void
    {
        hook(
            Shipping::class,
            'collectRates',
            static function (
                Shipping  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s %s', self::SPAN_NAME_PREFIX, 'collectRates');
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                );

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Shipping     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
